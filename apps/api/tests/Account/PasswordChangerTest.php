<?php

namespace App\Tests\Account;

use App\Entity\User;
use App\Service\Account\PasswordChanger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PasswordChangerTest extends TestCase
{
    public function testPasswordIsChangedWhenCurrentPasswordIsValid(): void
    {
        $user = new User('password@example.com', 'password_user', new \DateTimeImmutable('1990-01-01'));
        $user->setPassword('old-hash');

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher
            ->expects($this->once())
            ->method('isPasswordValid')
            ->with($user, 'current-password')
            ->willReturn(true)
        ;
        $passwordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->with($user, 'new-secure-password')
            ->willReturn('new-hash')
        ;

        $result = new PasswordChanger($passwordHasher)->change($user, 'current-password', 'new-secure-password');

        self::assertTrue($result->changed);
        self::assertSame('new-hash', $user->getPassword());
    }

    public function testPasswordIsNotChangedWhenCurrentPasswordIsInvalid(): void
    {
        $user = new User('password@example.com', 'password_user', new \DateTimeImmutable('1990-01-01'));
        $user->setPassword('old-hash');

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher
            ->expects($this->once())
            ->method('isPasswordValid')
            ->with($user, 'wrong-password')
            ->willReturn(false)
        ;
        $passwordHasher
            ->expects($this->never())
            ->method('hashPassword')
        ;

        $result = new PasswordChanger($passwordHasher)->change($user, 'wrong-password', 'new-secure-password');

        self::assertFalse($result->changed);
        self::assertSame('old-hash', $user->getPassword());
    }
}
