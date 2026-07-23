<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ProfileController extends AbstractController
{
    #[Route('/api/users/{username}', name: 'api_public_profile_show', requirements: ['username' => '[A-Za-z0-9_]{3,50}'], methods: ['GET'])]
    public function show(string $username, UserRepository $userRepository): JsonResponse
    {
        $user = $userRepository->findOnePublicByUsername($username);
        if (!$user instanceof User) {
            throw $this->createNotFoundException('User profile not found.');
        }

        return $this->json($this->publicPayload($user));
    }

    /**
     * @return array<string, mixed>
     */
    private function publicPayload(User $user): array
    {
        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'bio' => $user->getBio(),
            'avatarPath' => $user->getAvatarPath(),
            'createdAt' => $user->getCreatedAt()->format(DATE_ATOM),
        ];
    }
}
