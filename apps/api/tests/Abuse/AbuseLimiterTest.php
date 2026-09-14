<?php

namespace App\Tests\Abuse;

use App\Command\PruneAbuseCountersCommand;
use App\Service\Abuse\AbuseLimiter;
use App\Service\Abuse\CounterCache;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Process\Process;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

final class AbuseLimiterTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/abuse-test-'.bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public function testWindowExpiresAndRecreatingServiceDoesNotResetIt(): void
    {
        $this->limiter()->consume(['test' => 'visitor']);
        try {
            $this->limiter()->consume(['test' => 'visitor']);
            self::fail('A fresh service must reuse the stored counter.');
        } catch (TooManyRequestsHttpException $exception) {
            self::assertGreaterThan(0, (int) $exception->getHeaders()['Retry-After']);
        }
        $this->limiter()->consume(['test' => 'other visitor']);
        sleep(2);
        $this->limiter()->consume(['test' => 'visitor']);
    }

    public function testConcurrentProcessesCannotOverspendAndRestartPreservesQuota(): void
    {
        $code = <<<'PHP'
            require 'vendor/autoload.php';
            $directory = $argv[1];
            $service = new App\Service\Abuse\AbuseLimiter(
                new Symfony\Component\RateLimiter\Storage\CacheStorage(new Symfony\Component\Cache\Adapter\FilesystemAdapter('test', 0, $directory.'/counters')),
                new Symfony\Component\Lock\LockFactory(new Symfony\Component\Lock\Store\FlockStore($directory.'/locks')),
                ['test' => ['limit' => 3, 'interval' => '1 minute']],
                'test-secret',
            );
            try {
                $service->consume(['test' => 'same-user']);
                echo 'accepted';
            } catch (Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException) {
                echo 'limited';
            }
            PHP;
        $processes = [];
        for ($i = 0; $i < 10; ++$i) {
            $process = new Process([PHP_BINARY, '-r', $code, $this->directory], dirname(__DIR__, 2));
            $process->start();
            $processes[] = $process;
        }
        $accepted = 0;
        foreach ($processes as $process) {
            self::assertSame(0, $process->wait(), $process->getErrorOutput());
            $accepted += 'accepted' === $process->getOutput() ? 1 : 0;
        }
        self::assertSame(3, $accepted);
        $restart = new Process([PHP_BINARY, '-r', $code, $this->directory], dirname(__DIR__, 2));
        $restart->mustRun();
        self::assertSame('limited', $restart->getOutput());
    }

    public function testPruningDoesNotResetActiveQuotas(): void
    {
        $cache = new CounterCache('test', 0, $this->directory.'/counters');
        $locks = new LockFactory(new FlockStore($this->directory.'/locks'));
        $limiter = new AbuseLimiter(new CacheStorage($cache), $locks, ['test' => ['limit' => 1, 'interval' => '1 minute']], 'test-secret');
        $limiter->consume(['test' => 'visitor']);
        self::assertSame(0, (new CommandTester(new PruneAbuseCountersCommand($cache, $locks)))->execute([]));
        $this->expectException(TooManyRequestsHttpException::class);
        $limiter->consume(['test' => 'visitor']);
    }

    public function testFailedCounterWriteFailsClosed(): void
    {
        $path = $this->directory.'/counters';
        $cache = new CounterCache('test', 0, $path);
        $item = $cache->getItem('counter');
        $item->set('state');
        // Simulate a filesystem failure after opening the cache.
        (new Filesystem())->remove($path);
        file_put_contents($path, 'not a directory');
        $this->expectException(ServiceUnavailableHttpException::class);
        $cache->save($item);
    }

    private function limiter(): AbuseLimiter
    {
        return new AbuseLimiter(
            new CacheStorage(new FilesystemAdapter('test', 0, $this->directory.'/counters')),
            new LockFactory(new FlockStore($this->directory.'/locks')),
            ['test' => ['limit' => 1, 'interval' => '1 second']],
            'test-secret',
        );
    }
}
