<?php

namespace App\Command;

use App\Service\Abuse\CounterCache;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:abuse:prune', description: 'Remove expired abuse counters without resetting active quotas.')]
final class PruneAbuseCountersCommand extends Command
{
    public function __construct(#[Autowire(service: 'app.abuse_cache')] private readonly CounterCache $cache)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->cache->prune() ? Command::SUCCESS : Command::FAILURE;
    }
}
