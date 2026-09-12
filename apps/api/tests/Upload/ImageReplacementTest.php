<?php

namespace App\Tests\Upload;

use App\Entity\Recipe;
use App\Entity\User;
use App\Service\Upload\ImageReplacement;
use App\Service\Upload\LocalImageStorage;
use App\Service\Upload\UploadLock;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ImageReplacementTest extends KernelTestCase
{
    private string $directory;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->directory = sys_get_temp_dir().'/replacement-'.bin2hex(random_bytes(8));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
        parent::tearDown();
    }

    #[DataProvider('owners')]
    public function testReplacementAndRemovalCommitBeforeDeletingOldFile(bool $recipe): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $owner = $this->owner($em, $recipe);
        $storage = new LocalImageStorage($this->directory, '/uploads/test', 10000, 'Test');
        $old = $storage->store($this->upload());
        $this->setPath($owner, $old);
        $em->flush();
        $listener = new class($this->directory.'/'.basename($old)) {
            public function __construct(private ?string $old)
            {
            }

            public function postFlush(): void
            {
                // postFlush is still inside the transaction.
                \PHPUnit\Framework\Assert::assertIsString($this->old);
                \PHPUnit\Framework\Assert::assertFileExists($this->old);
            }
        };
        $em->getEventManager()->addEventListener([Events::postFlush], $listener);
        $service = new ImageReplacement($em, new NullLogger(), new UploadLock($this->directory.'/.lock'));
        $service->replace($owner, $storage, $this->upload());
        $em->getEventManager()->removeEventListener([Events::postFlush], $listener);
        $new = $this->path($owner);
        self::assertIsString($new);
        self::assertNotSame($old, $new);
        self::assertFileDoesNotExist($this->directory.'/'.basename($old));
        self::assertFileExists($this->directory.'/'.basename($new));
        $em->refresh($owner);
        self::assertSame($new, $this->path($owner));
        $service->replace($owner, $storage, null);
        self::assertNull($this->path($owner));
        self::assertFileDoesNotExist($this->directory.'/'.basename($new));
    }

    #[DataProvider('failureCases')]
    public function testPersistenceFailurePreservesOldFileAndRemovesNewOrphan(bool $recipe, bool $delete): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $owner = $this->owner($em, $recipe);
        $storage = new LocalImageStorage($this->directory, '/uploads/test', 10000, 'Test');
        $old = $storage->store($this->upload());
        $this->setPath($owner, $old);
        $em->flush();
        $listener = new class {
            public function onFlush(): never
            {
                throw new \RuntimeException('Simulated persistence failure');
            }
        };
        $em->getEventManager()->addEventListener([Events::onFlush], $listener);
        try {
            (new ImageReplacement($em, new NullLogger(), new UploadLock($this->directory.'/.lock')))->replace($owner, $storage, $delete ? null : $this->upload());
            self::fail('Expected persistence failure.');
        } catch (\RuntimeException $e) {
            self::assertSame('Simulated persistence failure', $e->getMessage());
        } finally {
            $em->getEventManager()->removeEventListener([Events::onFlush], $listener);
        }
        self::assertFalse($em->getConnection()->isTransactionActive());
        self::assertSame($old, $this->path($owner));
        self::assertSame([$old], array_keys(iterator_to_array($storage->files())));
        $table = $recipe ? 'recipe' : '"user"';
        $column = $recipe ? 'image_path' : 'avatar_path';
        self::assertSame($old, $em->getConnection()->fetchOne('SELECT '.$column.' FROM '.$table.' WHERE id = ?', [$owner->getId()]));
    }

    /** @return iterable<array{bool}> */
    public static function owners(): iterable
    {
        yield 'avatar' => [false];
        yield 'recipe' => [true];
    }

    /** @return iterable<array{bool, bool}> */
    public static function failureCases(): iterable
    {
        yield 'avatar replacement' => [false, false];
        yield 'avatar removal' => [false, true];
        yield 'recipe replacement' => [true, false];
        yield 'recipe removal' => [true, true];
    }

    private function owner(EntityManagerInterface $em, bool $recipe): User|Recipe
    {
        $suffix = bin2hex(random_bytes(6));
        $user = new User($suffix.'@example.com', 'image_'.$suffix, new \DateTimeImmutable('1990-01-01'));
        $user->setPassword('unused');
        $em->persist($user);
        $owner = $user;
        if ($recipe) {
            $owner = new Recipe();
            $owner->setAuthor($user);
            $owner->setTitle('Image '.$suffix);
            $owner->setDescription('Image lifecycle test');
            $em->persist($owner);
        }
        $em->flush();

        return $owner;
    }

    private function path(User|Recipe $owner): ?string
    {
        return $owner instanceof User ? $owner->getAvatarPath() : $owner->getImagePath();
    }

    private function setPath(User|Recipe $owner, ?string $path): void
    {
        if ($owner instanceof User) {
            $owner->setAvatarPath($path);
        } else {
            $owner->setImagePath($path);
        }
    }

    private function upload(): UploadedFile
    {
        $path = $this->directory.'/source.png';
        imagepng(imagecreatetruecolor(2, 2), $path);

        return new UploadedFile($path, 'image.png', 'image/png', test: true);
    }
}
