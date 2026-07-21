<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\User\UserInterface;

final class AlcoholAccessPolicy
{
    public function canAccessAlcohol(?UserInterface $user, ?\DateTimeImmutable $now = null): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        $now ??= new \DateTimeImmutable('today');

        return $user->getBirthDate() <= $now->modify('-18 years');
    }
}
