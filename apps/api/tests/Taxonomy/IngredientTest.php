<?php

namespace App\Tests\Taxonomy;

use App\Entity\Ingredient;
use PHPUnit\Framework\TestCase;

final class IngredientTest extends TestCase
{
    public function testSlugIsGeneratedFromNameWhenMissing(): void
    {
        $ingredient = new Ingredient();

        $ingredient->setName('Fresh Lime Juice');

        self::assertSame('fresh-lime-juice', $ingredient->getSlug());
    }

    public function testExplicitSlugIsNormalizedAndPreservedWhenNameChanges(): void
    {
        $ingredient = new Ingredient();

        $ingredient->setSlug(' Citrus  Peel ');
        $ingredient->setName('Lemon peel');

        self::assertSame('citrus-peel', $ingredient->getSlug());
    }

    public function testIngredientDoesNotContainAlcoholByDefault(): void
    {
        $ingredient = new Ingredient();

        self::assertFalse($ingredient->containsAlcohol());
    }
}
