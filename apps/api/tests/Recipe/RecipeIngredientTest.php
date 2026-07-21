<?php

namespace App\Tests\Recipe;

use App\Entity\Ingredient;
use App\Entity\Recipe;
use App\Entity\RecipeIngredient;
use App\Enum\IngredientUnit;
use PHPUnit\Framework\TestCase;

final class RecipeIngredientTest extends TestCase
{
    public function testQuantityIsNormalizedToDecimalString(): void
    {
        $recipeIngredient = new RecipeIngredient();

        $recipeIngredient->setQuantity('4.5');

        self::assertSame('4.50', $recipeIngredient->getQuantity());
    }

    public function testNullQuantityIsAllowed(): void
    {
        $recipeIngredient = new RecipeIngredient();

        $recipeIngredient->setQuantity(null);

        self::assertNull($recipeIngredient->getQuantity());
    }

    public function testEmptyNoteIsStoredAsNull(): void
    {
        $recipeIngredient = new RecipeIngredient();

        $recipeIngredient->setNote('   ');

        self::assertNull($recipeIngredient->getNote());
    }

    public function testRecipeAlcoholFlagIsComputedFromIngredients(): void
    {
        $gin = new Ingredient();
        $gin->setName('Gin');
        $gin->setContainsAlcohol(true);
        $recipe = new Recipe();
        $recipeIngredient = new RecipeIngredient();
        $recipeIngredient->setIngredient($gin);

        $recipe->addRecipeIngredient($recipeIngredient);

        self::assertTrue($recipeIngredient->containsAlcohol());
        self::assertTrue($recipe->containsAlcoholComputed());
    }

    public function testRecipeIngredientStoresUnit(): void
    {
        $recipeIngredient = new RecipeIngredient();

        $recipeIngredient->setUnit(IngredientUnit::BarSpoon);

        self::assertSame(IngredientUnit::BarSpoon, $recipeIngredient->getUnit());
    }
}
