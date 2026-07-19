<?php

namespace App\Tests\Taxonomy;

use App\Entity\Category;
use PHPUnit\Framework\TestCase;

final class CategoryTest extends TestCase
{
    public function testSlugIsGeneratedFromNameWhenMissing(): void
    {
        $category = new Category();

        $category->setName('Classic Cocktails');

        self::assertSame('classic-cocktails', $category->getSlug());
    }

    public function testExplicitSlugIsNormalizedAndPreservedWhenNameChanges(): void
    {
        $category = new Category();

        $category->setSlug(' Summer  Drinks ');
        $category->setName('Seasonal drinks');

        self::assertSame('summer-drinks', $category->getSlug());
    }

    public function testEmptyDescriptionIsStoredAsNull(): void
    {
        $category = new Category();

        $category->setDescription('   ');

        self::assertNull($category->getDescription());
    }
}
