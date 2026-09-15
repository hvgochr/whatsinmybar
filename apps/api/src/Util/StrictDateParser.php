<?php

namespace App\Util;

final class StrictDateParser
{
    public static function yearMonthDay(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || 1 !== preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date instanceof \DateTimeImmutable && $date->format('Y-m-d') === $value ? $date : null;
    }
}
