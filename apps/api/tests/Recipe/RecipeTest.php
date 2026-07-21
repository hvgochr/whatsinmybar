<?php

namespace App\Tests\Recipe;

use App\Entity\Category;
use App\Entity\Recipe;
use App\Entity\User;
use App\Enum\RecipeStatus;
use PHPUnit\Framework\TestCase;

final class RecipeTest extends TestCase
{
    public function testSlugIsGeneratedFromTitleWhenMissing(): void
    {
        $recipe = new Recipe();

        $recipe->setTitle('Old Fashioned');

        self::assertSame('old-fashioned', $recipe->getSlug());
    }

    public function testPublishingSetsPublishedAtOnce(): void
    {
        $recipe = new Recipe();

        $recipe->setStatus(RecipeStatus::Published);
        $publishedAt = $recipe->getPublishedAt();
        $recipe->setStatus(RecipeStatus::Draft);
        $recipe->setStatus(RecipeStatus::Published);

        self::assertInstanceOf(\DateTimeImmutable::class, $publishedAt);
        self::assertSame($publishedAt, $recipe->getPublishedAt());
    }

    public function testPublishedRecipeIsVisibleUntilDeleted(): void
    {
        $recipe = new Recipe();
        $recipe->setStatus(RecipeStatus::Published);

        self::assertTrue($recipe->canBeViewedBy(null));

        $recipe->softDelete();

        self::assertFalse($recipe->canBeViewedBy(null));
    }

    public function testDraftRecipeIsOnlyVisibleToAuthorOrAdmin(): void
    {
        $author = $this->user('author@example.com', 'author');
        $otherUser = $this->user('reader@example.com', 'reader');
        $admin = $this->user('admin@example.com', 'admin');
        $admin->setRoles(['ROLE_ADMIN']);

        $recipe = new Recipe();
        $recipe->setAuthor($author);

        self::assertFalse($recipe->canBeViewedBy(null));
        self::assertFalse($recipe->canBeViewedBy($otherUser));
        self::assertTrue($recipe->canBeViewedBy($author));
        self::assertTrue($recipe->canBeViewedBy($admin));
    }

    public function testCategoriesAreUnique(): void
    {
        $category = new Category();
        $category->setName('Classics');
        $recipe = new Recipe();

        $recipe->addCategory($category);
        $recipe->addCategory($category);

        self::assertCount(1, $recipe->getCategories());
    }

    private function user(string $email, string $username): User
    {
        return new User($email, $username, new \DateTimeImmutable('1990-01-01'));
    }
}
