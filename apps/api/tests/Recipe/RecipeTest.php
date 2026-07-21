<?php

namespace App\Tests\Recipe;

use App\Entity\Category;
use App\Entity\Recipe;
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

    public function testSoftDeleteSetsDeletedAt(): void
    {
        $recipe = new Recipe();

        $recipe->softDelete();

        self::assertInstanceOf(\DateTimeImmutable::class, $recipe->getDeletedAt());
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

    public function testAlcoholFlagUsesOverrideBeforeComputedValue(): void
    {
        $recipe = new Recipe();
        $recipe->setContainsAlcoholComputed(true);

        self::assertTrue($recipe->containsAlcohol());
        self::assertTrue($recipe->getContainsAlcohol());

        $recipe->setContainsAlcoholOverride(false);

        self::assertFalse($recipe->containsAlcohol());
        self::assertFalse($recipe->getContainsAlcohol());
    }
}
