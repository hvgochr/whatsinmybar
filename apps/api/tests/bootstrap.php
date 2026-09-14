<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

// Real filesystem quotas survive kernel reboots within a test, but independent
// tests/runs must not spend each other's budgets or touch development counters.
$_ENV['SYMFONY_TRUSTED_PROXIES'] = $_SERVER['SYMFONY_TRUSTED_PROXIES'] = '172.30.71.2,172.30.71.3';
$_ENV['ABUSE_TEST_DIRECTORY'] = dirname(__DIR__).'/var/abuse/tests-'.getmypid();
PHPUnit\Event\Facade::instance()->registerSubscriber(new class implements PHPUnit\Event\Test\PreparationStartedSubscriber {
    public function notify(PHPUnit\Event\Test\PreparationStarted $event): void
    {
        (new Symfony\Component\Cache\Adapter\FilesystemAdapter('abuse', 0, $_ENV['ABUSE_TEST_DIRECTORY'].'/counters'))->clear();
    }
});

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
