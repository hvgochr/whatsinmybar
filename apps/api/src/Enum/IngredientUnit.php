<?php

namespace App\Enum;

enum IngredientUnit: string
{
    case Milliliter = 'ml';
    case Centiliter = 'cl';
    case Liter = 'l';
    case Ounce = 'oz';
    case Dash = 'dash';
    case BarSpoon = 'bar_spoon';
    case Teaspoon = 'tsp';
    case Tablespoon = 'tbsp';
    case Drop = 'drop';
    case Piece = 'piece';
    case Slice = 'slice';
    case Wedge = 'wedge';
    case Leaf = 'leaf';
    case Sprig = 'sprig';
    case Pinch = 'pinch';
    case ToTaste = 'to_taste';

    public function label(): string
    {
        return match ($this) {
            self::Milliliter => 'Milliliter',
            self::Centiliter => 'Centiliter',
            self::Liter => 'Liter',
            self::Ounce => 'Ounce',
            self::Dash => 'Dash',
            self::BarSpoon => 'Bar spoon',
            self::Teaspoon => 'Teaspoon',
            self::Tablespoon => 'Tablespoon',
            self::Drop => 'Drop',
            self::Piece => 'Piece',
            self::Slice => 'Slice',
            self::Wedge => 'Wedge',
            self::Leaf => 'Leaf',
            self::Sprig => 'Sprig',
            self::Pinch => 'Pinch',
            self::ToTaste => 'To taste',
        };
    }
}
