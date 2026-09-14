<?php

namespace App\Service\Abuse;

use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

final class AbuseLimiter
{
    /** @param array<string, array{limit: int, interval: string}> $policies */
    public function __construct(
        private readonly StorageInterface $storage,
        private readonly LockFactory $locks,
        private readonly array $policies,
        private readonly string $secret,
    ) {
    }

    /** @param array<string, string> $identities Policy => identity, never supplied by the caller's payload for authenticated writes. */
    public function consume(array $identities): void
    {
        // One local lock bounds lock-file cardinality and makes combined budgets atomic.
        // Keep only counter IO inside this critical section, never application work.
        $lock = $this->locks->createLock('abuse-counters');
        $lock->acquire(true);
        try {
            $limiters = [];
            $retryAfter = 0;
            foreach ($identities as $policy => $identity) {
                $factory = new RateLimiterFactory([
                    'id' => $policy,
                    'policy' => 'fixed_window',
                    ...$this->policies[$policy],
                ], $this->storage);
                $limiter = $factory->create(hash_hmac('sha256', $identity, $this->secret));
                $budget = $limiter->consume(0);
                if (0 === $budget->getRemainingTokens()) {
                    $retryAfter = max($retryAfter, $budget->getRetryAfter()->getTimestamp() - time() + 1);
                }
                $limiters[] = $limiter;
            }
            if ($retryAfter > 0) {
                throw new TooManyRequestsHttpException($retryAfter, 'Too many requests. Please try again later.');
            }
            foreach ($limiters as $limiter) {
                $limiter->consume();
            }
        } finally {
            $lock->release();
        }
    }
}
