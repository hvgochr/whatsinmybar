<?php

namespace App\Service;

use App\Repository\UserRepository;
use Doctrine\DBAL\Connection;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class RefreshSession
{
    public const GRACE_SECONDS = 10;

    public function __construct(
        private Connection $connection,
        private UserRepository $users,
        private JWTTokenManagerInterface $jwt,
        #[Autowire('%gesdinet_jwt_refresh_token.ttl%')]
        private int $ttl,
    ) {
    }

    /** @return array{token: string, refresh: string, expires: int}|null */
    public function rotate(string $cookie): ?array
    {
        if ('' === $cookie || strlen($cookie) > 128) {
            return null;
        }

        return $this->connection->transactional(function () use ($cookie): ?array {
            $this->connection->executeStatement("SET LOCAL lock_timeout = '2s'");
            // Updating the same row makes rotation and concurrent revocation serialize.
            $row = $this->connection->fetchAssociative(
                'SELECT * FROM refresh_tokens WHERE refresh_token = ? OR previous_token_hash = ? FOR UPDATE',
                [$cookie, hash('sha256', $cookie)],
            );
            $now = time();
            if (false === $row || strtotime((string) $row['valid']) <= $now) {
                return null;
            }
            $inGrace = (int) $row['rotation_grace_until'] > $now;
            if (!hash_equals((string) $row['refresh_token'], $cookie) && !$inGrace) {
                return null;
            }
            $user = $this->users->findOneBy(['email' => $row['username']]);
            if (null === $user || null !== $user->getDeletedAt()) {
                return null;
            }

            $current = (string) $row['refresh_token'];
            $expires = (int) strtotime((string) $row['valid']);
            if (!$inGrace) {
                $current = bin2hex(random_bytes(64));
                $expires = $now + $this->ttl;
                $this->connection->update('refresh_tokens', [
                    'refresh_token' => $current,
                    'previous_token_hash' => hash('sha256', $cookie),
                    'rotation_grace_until' => $now + self::GRACE_SECONDS,
                    'valid' => date('Y-m-d H:i:s', $expires),
                ], ['id' => $row['id']]);
            }

            // Never extend grace or expiry when repeating a rotation, even with the successor.
            return ['token' => $this->jwt->create($user), 'refresh' => $current, 'expires' => $expires];
        });
    }

    public function revoke(string $cookie): void
    {
        // The predecessor can revoke its successor, but cannot refresh beyond grace.
        $this->connection->executeStatement(
            'DELETE FROM refresh_tokens WHERE refresh_token = ? OR previous_token_hash = ?',
            [$cookie, hash('sha256', $cookie)],
        );
    }
}
