<?php

use App\Entity\User;
use App\Kernel;
use App\Service\ActiveAdminGuard;
use App\Service\UserAccountAccess;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
$_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] = '1';
(new Dotenv())->bootEnv(dirname(__DIR__, 2).'/.env');

[$userId, $action, $barrier, $worker] = array_slice($argv, 1);
$kernel = new Kernel('test', true);
$kernel->boot();

try {
    /** @var ManagerRegistry $doctrine */
    $doctrine = $kernel->getContainer()->get('doctrine');
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $doctrine->getManager();
    $user = $entityManager->find(User::class, (int) $userId);
    if (!$user instanceof User || !in_array($action, ['delete', 'demote'], true)) {
        throw new RuntimeException('Concurrent administrator mutation fixture is invalid.');
    }

    file_put_contents($barrier.'.ready.'.$worker, 'ready');
    $deadline = microtime(true) + 10;
    while (!is_file($barrier)) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('Concurrent administrator mutation barrier timed out.');
        }

        usleep(1_000);
    }

    try {
        $entityManager->wrapInTransaction(function () use ($action, $entityManager, $user): void {
            $roles = 'demote' === $action ? [] : $user->getRoles();
            $deleted = 'delete' === $action;
            (new ActiveAdminGuard($entityManager->getConnection()))->assertCanApply($user, $roles, $deleted);

            if ('demote' === $action) {
                $user->setRoles([]);
            } else {
                (new UserAccountAccess($entityManager))->setDeleted($user, true);
            }

            $entityManager->flush();
        });
        echo json_encode(['result' => 'applied'], JSON_THROW_ON_ERROR);
    } catch (ConflictHttpException) {
        echo json_encode(['result' => 'conflict'], JSON_THROW_ON_ERROR);
    }
} finally {
    $kernel->shutdown();
}
