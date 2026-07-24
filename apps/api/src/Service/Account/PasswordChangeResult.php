<?php

namespace App\Service\Account;

final readonly class PasswordChangeResult
{
    private function __construct(public bool $changed)
    {
    }

    public static function changed(): self
    {
        return new self(true);
    }

    public static function invalidCurrentPassword(): self
    {
        return new self(false);
    }
}
