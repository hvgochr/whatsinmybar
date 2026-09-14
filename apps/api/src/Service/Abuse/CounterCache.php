<?php

namespace App\Service\Abuse;

use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/** Counter writes must not silently fail open when the disk is full. */
final class CounterCache extends FilesystemAdapter
{
    public function save(CacheItemInterface $item): bool
    {
        if (!parent::save($item)) {
            throw new ServiceUnavailableHttpException(60, 'Rate limit storage is unavailable.');
        }

        return true;
    }
}
