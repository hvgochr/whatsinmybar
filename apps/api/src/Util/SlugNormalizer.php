<?php

namespace App\Util;

final class SlugNormalizer
{
    private function __construct()
    {
    }

    public static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

        return trim($value, '-');
    }
}
