<?php

namespace App\Service\Upload;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class UploadLock
{
    public function __construct(#[Autowire('%app.avatar_uploads_dir%/../.maintenance.lock')] private string $path)
    {
    }

    /** @param callable(): void $work */
    public function run(bool $exclusive, callable $work): void
    {
        $directory = dirname($this->path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create upload lock directory.');
        }
        $handle = fopen($this->path, 'c');
        if (false === $handle) {
            throw new \RuntimeException('Unable to open upload maintenance lock.');
        }
        try {
            if (!flock($handle, $exclusive ? LOCK_EX : LOCK_SH)) {
                throw new \RuntimeException('Unable to acquire upload maintenance lock.');
            }
            $work();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
