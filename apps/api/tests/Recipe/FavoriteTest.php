<?php

namespace App\Tests\Recipe;

use App\Entity\Favorite;
use App\Entity\Recipe;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class FavoriteTest extends TestCase
{
    public function testFavoriteReferencesUserAndRecipe(): void
    {
        $user = new User('favorite@example.com', 'favorite_user', new \DateTimeImmutable('1990-01-01'));
        $recipe = new Recipe();

        $favorite = new Favorite($user, $recipe);

        self::assertSame($user, $favorite->getUser());
        self::assertSame($recipe, $favorite->getRecipe());
        self::assertInstanceOf(\DateTimeImmutable::class, $favorite->getCreatedAt());
    }

    public function testRecipeFavoriteCountCannotGoBelowZero(): void
    {
        $recipe = new Recipe();

        $recipe->decrementFavoriteCount();

        self::assertSame(0, $recipe->getFavoriteCount());

        $recipe->incrementFavoriteCount();
        $recipe->decrementFavoriteCount();

        self::assertSame(0, $recipe->getFavoriteCount());
    }
}
