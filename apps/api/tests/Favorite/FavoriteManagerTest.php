<?php

namespace App\Tests\Favorite;

use App\Entity\Recipe;
use App\Entity\User;
use App\Enum\RecipeStatus;
use App\Repository\FavoriteRepository;
use App\Service\FavoriteManager;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class FavoriteManagerTest extends KernelTestCase
{
    public function testAddAndRemoveAreIdempotent(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $favoriteRepository = self::getContainer()->get(FavoriteRepository::class);
        $favoriteManager = new FavoriteManager(self::getContainer()->get(Connection::class));
        $user = $this->createUser();
        $recipe = $this->createRecipe($user);

        $entityManager->persist($user);
        $entityManager->persist($recipe);
        $entityManager->flush();

        $firstAdd = $favoriteManager->add($user, $recipe);
        $secondAdd = $favoriteManager->add($user, $recipe);

        self::assertTrue($firstAdd->favorited);
        self::assertTrue($firstAdd->changed);
        self::assertSame(1, $firstAdd->favoriteCount);
        self::assertTrue($secondAdd->favorited);
        self::assertFalse($secondAdd->changed);
        self::assertSame(1, $secondAdd->favoriteCount);
        self::assertSame(1, $recipe->getFavoriteCount());
        self::assertCount(1, $favoriteRepository->findBy(['user' => $user, 'recipe' => $recipe]));

        $firstRemove = $favoriteManager->remove($user, $recipe);
        $secondRemove = $favoriteManager->remove($user, $recipe);

        self::assertFalse($firstRemove->favorited);
        self::assertTrue($firstRemove->changed);
        self::assertSame(0, $firstRemove->favoriteCount);
        self::assertFalse($secondRemove->favorited);
        self::assertFalse($secondRemove->changed);
        self::assertSame(0, $secondRemove->favoriteCount);
        self::assertSame(0, $recipe->getFavoriteCount());
        self::assertCount(0, $favoriteRepository->findBy(['user' => $user, 'recipe' => $recipe]));
    }

    public function testConcurrentAddsForSameFavoriteRemainIdempotent(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $user = $this->createUser();
        $recipe = $this->createRecipe($user);
        $entityManager->persist($user);
        $entityManager->persist($recipe);
        $entityManager->flush();

        $results = $this->runConcurrentAdds([$user, $user], $recipe);

        self::assertSame([false, true], array_map(static fn (array $result): bool => $result['changed'], $results));
        self::assertSame(1, $entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM favorite WHERE recipe_id = ?', [$recipe->getId()]));
        self::assertSame(1, $entityManager->getConnection()->fetchOne('SELECT favorite_count FROM recipe WHERE id = ?', [$recipe->getId()]));
    }

    public function testConcurrentAddsForDifferentUsersDoNotLoseCounterUpdates(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $author = $this->createUser();
        $otherUser = $this->createUser();
        $recipe = $this->createRecipe($author);
        $entityManager->persist($author);
        $entityManager->persist($otherUser);
        $entityManager->persist($recipe);
        $entityManager->flush();

        $results = $this->runConcurrentAdds([$author, $otherUser], $recipe);

        self::assertSame([true, true], array_map(static fn (array $result): bool => $result['changed'], $results));
        self::assertSame(2, $entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM favorite WHERE recipe_id = ?', [$recipe->getId()]));
        self::assertSame(2, $entityManager->getConnection()->fetchOne('SELECT favorite_count FROM recipe WHERE id = ?', [$recipe->getId()]));
    }

    /**
     * @param array{User, User} $users
     *
     * @return list<array{changed: bool, favoriteCount: int}>
     */
    private function runConcurrentAdds(array $users, Recipe $recipe): array
    {
        $barrier = sys_get_temp_dir().'/favorite-concurrency-'.bin2hex(random_bytes(8));
        $processes = [];

        try {
            foreach ($users as $index => $user) {
                $pipes = [];
                $process = proc_open([
                    PHP_BINARY,
                    dirname(__DIR__).'/Support/concurrent-favorite-worker.php',
                    (string) $user->getId(),
                    (string) $recipe->getId(),
                    $barrier,
                    (string) $index,
                ], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
                self::assertIsResource($process);
                $processes[] = [$process, $pipes];
            }

            $deadline = microtime(true) + 10;
            while (!is_file($barrier.'.ready.0') || !is_file($barrier.'.ready.1')) {
                self::assertLessThan($deadline, microtime(true), 'Concurrent favorite workers did not become ready.');
                usleep(1_000);
            }
            file_put_contents($barrier, 'go');

            $results = [];
            foreach ($processes as [$process, $pipes]) {
                $stdout = stream_get_contents($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                self::assertSame(0, proc_close($process), $stderr ?: $stdout);
                $decoded = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
                self::assertIsArray($decoded);
                $results[] = $decoded;
            }

            usort($results, static fn (array $left, array $right): int => ($left['changed'] <=> $right['changed']));

            return $results;
        } finally {
            foreach ([$barrier, $barrier.'.ready.0', $barrier.'.ready.1'] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    private function createUser(): User
    {
        $suffix = bin2hex(random_bytes(6));

        $user = new User(
            sprintf('favorite-manager-%s@example.com', $suffix),
            sprintf('favorite_manager_%s', $suffix),
            new \DateTimeImmutable('1990-01-01'),
        );
        $user->setPassword('hashed-password');

        return $user;
    }

    private function createRecipe(User $author): Recipe
    {
        $suffix = bin2hex(random_bytes(6));

        $recipe = new Recipe();
        $recipe->setAuthor($author);
        $recipe->setTitle(sprintf('Favorite Manager Recipe %s', $suffix));
        $recipe->setDescription('Recipe used to test favorite manager idempotence.');
        $recipe->setStatus(RecipeStatus::Published);

        return $recipe;
    }
}
