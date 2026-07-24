<?php

namespace App\Service\Account;

use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class PasswordChanger
{
    public function __construct(private UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function change(User $user, string $currentPassword, string $newPassword): PasswordChangeResult
    {
        if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            return PasswordChangeResult::invalidCurrentPassword();
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));

        return PasswordChangeResult::changed();
    }
}
