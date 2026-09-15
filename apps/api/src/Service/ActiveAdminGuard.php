<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final readonly class ActiveAdminGuard
{
    private const LOCK_KEY = 8_126_041_993;

    public function __construct(private Connection $connection)
    {
    }

    /**
     * @param list<string> $proposedRoles
     */
    public function assertCanApply(User $user, array $proposedRoles, bool $proposedDeleted): void
    {
        if (!$this->connection->isTransactionActive()) {
            throw new \LogicException('The active administrator guard requires a transaction.');
        }

        $this->connection->executeQuery('SELECT pg_advisory_xact_lock(:lockKey)', ['lockKey' => self::LOCK_KEY]);
        $willRemainActiveAdmin = !$proposedDeleted && in_array('ROLE_ADMIN', $proposedRoles, true);
        if ($willRemainActiveAdmin) {
            return;
        }

        $userId = $user->getId();
        if (null === $userId) {
            throw new \LogicException('The active administrator guard requires a persisted user.');
        }

        $currentlyActiveAdmin = $this->connection->fetchOne(<<<'SQL'
            SELECT COUNT(*)
            FROM "user"
            WHERE id = :userId
              AND deleted_at IS NULL
              AND roles::jsonb @> CAST(:adminRole AS jsonb)
            SQL, ['userId' => $userId, 'adminRole' => '["ROLE_ADMIN"]']);
        if (!is_int($currentlyActiveAdmin) && !is_string($currentlyActiveAdmin)) {
            throw new \RuntimeException('Administrator state could not be read.');
        }
        if (0 === (int) $currentlyActiveAdmin) {
            return;
        }

        $activeAdminCount = $this->connection->fetchOne(<<<'SQL'
            SELECT COUNT(*)
            FROM "user"
            WHERE deleted_at IS NULL
              AND roles::jsonb @> CAST(:adminRole AS jsonb)
            SQL, ['adminRole' => '["ROLE_ADMIN"]']);

        if (!is_int($activeAdminCount) && !is_string($activeAdminCount)) {
            throw new \RuntimeException('Active administrator count could not be read.');
        }

        if ((int) $activeAdminCount <= 1) {
            throw new ConflictHttpException('The last active administrator cannot be deleted or demoted.');
        }
    }
}
