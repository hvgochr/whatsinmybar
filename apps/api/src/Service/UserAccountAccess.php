<?php

namespace App\Service;

use App\Entity\RefreshToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final readonly class UserAccountAccess
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function setDeleted(User $user, bool $deleted): void
    {
        $user->setDeletedAt($deleted ? ($user->getDeletedAt() ?? new \DateTimeImmutable()) : null);

        if (!$deleted) {
            return;
        }

        $this->revokeRefreshTokens($user);
    }

    public function revokeRefreshTokens(User $user): void
    {
        foreach ($this->entityManager->getRepository(RefreshToken::class)->findBy(['username' => $user->getUserIdentifier()]) as $refreshToken) {
            $this->entityManager->remove($refreshToken);
        }
    }
}
