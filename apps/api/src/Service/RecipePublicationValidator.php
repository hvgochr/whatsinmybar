<?php

namespace App\Service;

use App\Entity\Recipe;
use App\Enum\RecipeModerationStatus;
use App\Enum\RecipeStatus;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class RecipePublicationValidator
{
    public function __construct(private ValidatorInterface $validator)
    {
    }

    public function validateVisibilityTransition(Recipe $recipe, RecipeStatus $previousStatus, RecipeModerationStatus $previousModerationStatus): void
    {
        if (RecipeStatus::Published === $recipe->getStatus() && null === $recipe->getDeletedAt()
            && (RecipeStatus::Published !== $previousStatus
                || (RecipeModerationStatus::Visible !== $previousModerationStatus && RecipeModerationStatus::Visible === $recipe->getModerationStatus()))) {
            $this->validate($recipe);
        }
    }

    public function validate(Recipe $recipe): void
    {
        if ($recipe->getSteps()->isEmpty() || $recipe->getRecipeIngredients()->isEmpty()) {
            throw new UnprocessableEntityHttpException('Published recipes require at least one valid step and ingredient.');
        }

        foreach (['title', 'slug', 'description', 'preparationTimeMinutes', 'servings'] as $property) {
            if ($this->validator->validateProperty($recipe, $property)->count() > 0) {
                throw new UnprocessableEntityHttpException('Published recipe metadata is invalid.');
            }
        }

        foreach ([...$recipe->getSteps(), ...$recipe->getRecipeIngredients()] as $part) {
            if ($this->validator->validate($part)->count() > 0) {
                throw new UnprocessableEntityHttpException('Published recipe content is invalid.');
            }
        }

        foreach ([$recipe->getSteps(), $recipe->getRecipeIngredients()] as $parts) {
            $positions = [];
            foreach ($parts as $part) {
                if (isset($positions[$part->getPosition()])) {
                    throw new UnprocessableEntityHttpException('Recipe positions must be unique.');
                }
                $positions[$part->getPosition()] = true;
            }
        }
    }
}
