<?php

namespace App\Tests\Recipe;

use App\Entity\Recipe;
use App\Entity\RecipeStep;
use PHPUnit\Framework\TestCase;

final class RecipeStepTest extends TestCase
{
    public function testStepInstructionIsTrimmed(): void
    {
        $step = new RecipeStep();

        $step->setInstruction('  Stir with ice.  ');

        self::assertSame('Stir with ice.', $step->getInstruction());
    }

    public function testAddingStepLinksItToRecipe(): void
    {
        $recipe = new Recipe();
        $step = new RecipeStep();

        $recipe->addStep($step);

        self::assertSame($recipe, $step->getRecipe());
        self::assertTrue($recipe->getSteps()->contains($step));
    }

    public function testRemovingStepUnlinksItFromRecipe(): void
    {
        $recipe = new Recipe();
        $step = new RecipeStep();

        $recipe->addStep($step);
        $recipe->removeStep($step);

        self::assertNull($step->getRecipe());
        self::assertFalse($recipe->getSteps()->contains($step));
    }
}
