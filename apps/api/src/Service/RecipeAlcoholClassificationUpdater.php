<?php

namespace App\Service;

use App\Entity\Ingredient;
use App\Repository\RecipeRepository;

final readonly class RecipeAlcoholClassificationUpdater
{
    public function __construct(private RecipeRepository $recipeRepository)
    {
    }

    public function recalculateForIngredient(Ingredient $ingredient): void
    {
        if (null === $ingredient->getId()) {
            return;
        }

        foreach ($this->recipeRepository->findUsingIngredient($ingredient) as $recipe) {
            $recipe->recalculateContainsAlcohol();
        }
    }
}
