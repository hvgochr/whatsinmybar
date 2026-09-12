<?php

namespace App\Service\Upload;

use App\Entity\Recipe;
use App\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class ImageReplacement
{
    public function __construct(private EntityManagerInterface $entityManager, private LoggerInterface $logger, private UploadLock $lock)
    {
    }

    /** @param (callable(): void)|null $authorize */
    public function replace(User|Recipe $owner, ImageStorageInterface $storage, ?UploadedFile $file, ?callable $authorize = null): void
    {
        $this->lock->run(false, fn () => $this->replaceLocked($owner, $storage, $file, $authorize));
    }

    /** @param (callable(): void)|null $authorize */
    private function replaceLocked(User|Recipe $owner, ImageStorageInterface $storage, ?UploadedFile $file, ?callable $authorize): void
    {
        // This service must own the outer transaction: flush alone is not a commit.
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            throw new \LogicException('Image replacement requires its own transaction.');
        }
        $newPath = null;
        $oldPath = $owner instanceof User ? $owner->getAvatarPath() : $owner->getImagePath();
        try {
            $this->entityManager->wrapInTransaction(function () use ($owner, $storage, $file, $authorize, &$newPath, &$oldPath): void {
                $this->entityManager->refresh($owner, LockMode::PESSIMISTIC_WRITE);
                $oldPath = $owner instanceof User ? $owner->getAvatarPath() : $owner->getImagePath();
                if (null !== $owner->getDeletedAt()) {
                    throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
                }
                if (null !== $authorize) {
                    $authorize();
                }
                $newPath = null === $file ? null : $storage->store($file);
                $this->setPath($owner, $newPath);
            });
        } catch (\Throwable $exception) {
            $this->setPath($owner, $oldPath);
            $this->removeSafely($storage, $newPath);
            throw $exception;
        }
        $this->removeSafely($storage, $oldPath);
    }

    private function setPath(User|Recipe $owner, ?string $path): void
    {
        if ($owner instanceof User) {
            $owner->setAvatarPath($path);
        } else {
            $owner->setImagePath($path);
        }
    }

    private function removeSafely(ImageStorageInterface $storage, ?string $path): void
    {
        if (null === $path) {
            return;
        }
        try {
            // If commit acknowledgement was lost, retaining the file is safer
            // than deleting an image the database may have committed.
            $connection = $this->entityManager->getConnection();
            $references = $connection->fetchOne('SELECT (SELECT COUNT(*) FROM "user" WHERE avatar_path = ?) + (SELECT COUNT(*) FROM recipe WHERE image_path = ?)', [$path, $path]);
            if (0 === (int) $references) {
                $storage->remove($path);
            }
        } catch (\Throwable $exception) {
            // A cleanup failure must not hide a persistence error or undo a successful response.
            $this->logger->warning('Image cleanup deferred to orphan maintenance.', ['path' => $path, 'exception' => $exception]);
        }
    }
}
