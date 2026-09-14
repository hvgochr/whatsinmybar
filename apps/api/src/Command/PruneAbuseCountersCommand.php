<?php

namespace App\Command;

use App\Service\Abuse\AbuseLimiter;
use App\Service\Abuse\CounterCache;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Lock\LockFactory;

#[AsCommand(name: 'app:abuse:prune', description: 'Remove expired abuse counters without resetting active quotas.')]
final class PruneAbuseCountersCommand extends Command
{
    public function __construct(
        #[Autowire(service: 'app.abuse_cache')] private readonly CounterCache $cache,
        #[Autowire(service: 'app.abuse_locks')] private readonly LockFactory $locks,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Do not remove an expired file while a request replaces it with a new window.
        $lock = $this->locks->createLock(AbuseLimiter::LOCK_NAME);
        $lock->acquire(true);
        try {
            return $this->cache->prune() ? Command::SUCCESS : Command::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
