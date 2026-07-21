<?php

namespace App\Enum;

enum RecipeStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
