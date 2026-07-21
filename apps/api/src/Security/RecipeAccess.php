<?php

namespace App\Security;

final class RecipeAccess
{
    public const string Manage = 'RECIPE_MANAGE';
    public const string View = 'RECIPE_VIEW';

    private function __construct()
    {
    }
}
