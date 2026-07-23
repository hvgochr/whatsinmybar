<?php

namespace App\Tests\Profile;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class UserProfileTest extends TestCase
{
    public function testPublicIdentityFieldsAreNormalized(): void
    {
        $user = new User(' USER@example.com ', ' user_name ', new \DateTimeImmutable('1990-01-01'));

        self::assertSame('user@example.com', $user->getEmail());
        self::assertSame('user_name', $user->getUsername());
    }

    public function testOptionalProfileFieldsAreTrimmedAndBlankValuesBecomeNull(): void
    {
        $user = new User('user@example.com', 'user_name', new \DateTimeImmutable('1990-01-01'));

        $user->setBio(' Home bartender ');
        $user->setAvatarPath(' /uploads/avatars/user.jpg ');

        self::assertSame('Home bartender', $user->getBio());
        self::assertSame('/uploads/avatars/user.jpg', $user->getAvatarPath());

        $user->setBio(' ');
        $user->setAvatarPath('');

        self::assertNull($user->getBio());
        self::assertNull($user->getAvatarPath());
    }
}
