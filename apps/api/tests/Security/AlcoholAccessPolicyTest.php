<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Security\AlcoholAccessPolicy;
use PHPUnit\Framework\TestCase;

final class AlcoholAccessPolicyTest extends TestCase
{
    public function testAnonymousUserCannotAccessAlcohol(): void
    {
        $policy = new AlcoholAccessPolicy();

        self::assertFalse($policy->canAccessAlcohol(null, new \DateTimeImmutable('2026-07-21')));
    }

    public function testUserUnderEighteenCannotAccessAlcohol(): void
    {
        $policy = new AlcoholAccessPolicy();
        $user = new User('minor@example.com', 'minor', new \DateTimeImmutable('2009-07-22'));

        self::assertFalse($policy->canAccessAlcohol($user, new \DateTimeImmutable('2026-07-21')));
    }

    public function testUserAgedEighteenCanAccessAlcohol(): void
    {
        $policy = new AlcoholAccessPolicy();
        $user = new User('adult@example.com', 'adult', new \DateTimeImmutable('2008-07-21'));

        self::assertTrue($policy->canAccessAlcohol($user, new \DateTimeImmutable('2026-07-21')));
    }

    public function testAdminCanAccessAlcohol(): void
    {
        $policy = new AlcoholAccessPolicy();
        $admin = new User('admin@example.com', 'admin', new \DateTimeImmutable('2012-01-01'));
        $admin->setRoles(['ROLE_ADMIN']);

        self::assertTrue($policy->canAccessAlcohol($admin, new \DateTimeImmutable('2026-07-21')));
    }
}
