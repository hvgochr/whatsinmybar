<?php

namespace App\Tests\Favorite;

use App\Entity\Recipe;
use App\Entity\User;
use App\Enum\RecipeStatus;
use App\Repository\FavoriteRepository;
use App\Service\FavoriteManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class FavoriteManagerTest extends KernelTestCase
{
    public function testAddAndRemoveAreIdempotent(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $favoriteRepository = self::getContainer()->get(FavoriteRepository::class);
        $favoriteManager = new FavoriteManager($favoriteRepository, $entityManager);
        $user = $this->createUser();
        $recipe = $this->createRecipe($user);

        $entityManager->persist($user);
        $entityManager->persist($recipe);
        $entityManager->flush();

        $firstAdd = $favoriteManager->add($user, $recipe);
        $secondAdd = $favoriteManager->add($user, $recipe);

        self::assertTrue($firstAdd->favorited);
        self::assertTrue($firstAdd->changed);
        self::assertTrue($secondAdd->favorited);
        self::assertFalse($secondAdd->changed);
        self::assertSame(1, $recipe->getFavoriteCount());
        self::assertCount(1, $favoriteRepository->findBy(['user' => $user, 'recipe' => $recipe]));

        $firstRemove = $favoriteManager->remove($user, $recipe);
        $secondRemove = $favoriteManager->remove($user, $recipe);

        self::assertFalse($firstRemove->favorited);
        self::assertTrue($firstRemove->changed);
        self::assertFalse($secondRemove->favorited);
        self::assertFalse($secondRemove->changed);
        self::assertSame(0, $recipe->getFavoriteCount());
        self::assertCount(0, $favoriteRepository->findBy(['user' => $user, 'recipe' => $recipe]));
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
