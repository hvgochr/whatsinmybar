<?php

use App\Entity\Recipe;
use App\Entity\User;
use App\Kernel;
use App\Service\FavoriteManager;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
$_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] = '1';
(new Dotenv())->bootEnv(dirname(__DIR__, 2).'/.env');

[$userId, $recipeId, $barrier, $worker] = array_slice($argv, 1);
$kernel = new Kernel('test', true);
$kernel->boot();

try {
    /** @var ManagerRegistry $doctrine */
    $doctrine = $kernel->getContainer()->get('doctrine');
    $entityManager = $doctrine->getManager();
    $user = $entityManager->find(User::class, (int) $userId);
    $recipe = $entityManager->find(Recipe::class, (int) $recipeId);

    if (!$user instanceof User || !$recipe instanceof Recipe) {
        throw new RuntimeException('Concurrent favorite fixtures were not found.');
    }

    file_put_contents($barrier.'.ready.'.$worker, 'ready');
    $deadline = microtime(true) + 10;
    while (!is_file($barrier)) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('Concurrent favorite barrier timed out.');
        }

        usleep(1_000);
    }

    $result = (new FavoriteManager($entityManager->getConnection()))->add($user, $recipe);
    echo json_encode([
        'changed' => $result->changed,
        'favoriteCount' => $result->favoriteCount,
    ], JSON_THROW_ON_ERROR);
} finally {
    $kernel->shutdown();
}
