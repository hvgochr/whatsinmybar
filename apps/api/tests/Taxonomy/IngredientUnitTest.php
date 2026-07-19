<?php

namespace App\Tests\Taxonomy;

use App\Enum\IngredientUnit;
use PHPUnit\Framework\TestCase;

final class IngredientUnitTest extends TestCase
{
    public function testUnitsMatchSpecificationOrder(): void
    {
        self::assertSame([
            'ml',
            'cl',
            'l',
            'oz',
            'dash',
            'bar_spoon',
            'tsp',
            'tbsp',
            'drop',
            'piece',
            'slice',
            'wedge',
            'leaf',
            'sprig',
            'pinch',
            'to_taste',
        ], array_map(static fn (IngredientUnit $unit): string => $unit->value, IngredientUnit::cases()));
    }

    public function testUnitsExposeHumanReadableLabels(): void
    {
        self::assertSame('Bar spoon', IngredientUnit::BarSpoon->label());
        self::assertSame('To taste', IngredientUnit::ToTaste->label());
    }
}
