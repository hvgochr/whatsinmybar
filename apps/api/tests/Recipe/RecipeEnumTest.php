<?php

namespace App\Tests\Recipe;

use App\Enum\RecipeDifficulty;
use App\Enum\RecipeStatus;
use PHPUnit\Framework\TestCase;

final class RecipeEnumTest extends TestCase
{
    public function testRecipeStatusesMatchSpecification(): void
    {
        self::assertSame([
            'draft',
            'published',
            'archived',
        ], array_map(static fn (RecipeStatus $status): string => $status->value, RecipeStatus::cases()));
    }

    public function testRecipeDifficultiesAreStable(): void
    {
        self::assertSame([
            'easy',
            'medium',
            'hard',
        ], array_map(static fn (RecipeDifficulty $difficulty): string => $difficulty->value, RecipeDifficulty::cases()));
    }
}
