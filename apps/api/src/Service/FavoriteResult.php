<?php

namespace App\Service;

use App\Entity\Recipe;

final readonly class FavoriteResult
{
    public function __construct(
        public Recipe $recipe,
        public bool $favorited,
        public bool $changed,
    ) {
    }
}
