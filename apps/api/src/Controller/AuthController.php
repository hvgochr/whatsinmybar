<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AuthController extends AbstractController
{
    #[Route('/api/auth/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(
        Request $request,
        ValidatorInterface $validator,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $payload = $this->decodeJson($request);

        $violations = $validator->validate($payload, new Assert\Collection(
            fields: [
                'email' => new Assert\Required([new Assert\NotBlank(), new Assert\Email()]),
                'username' => new Assert\Required([
                    new Assert\NotBlank(),
                    new Assert\Length(min: 3, max: 50),
                    new Assert\Regex(pattern: '/^[a-zA-Z0-9_]+$/'),
                ]),
                'password' => new Assert\Required([new Assert\NotBlank(), new Assert\Length(min: 12, max: 4096)]),
                'birthDate' => new Assert\Required([new Assert\NotBlank(), new Assert\Date()]),
                'bio' => new Assert\Optional([new Assert\Length(max: 1000)]),
            ],
            allowExtraFields: false,
        ));

        if ($violations->count() > 0) {
            return $this->validationErrorResponse($violations);
        }

        $birthDate = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $payload['birthDate']);
        if (!$birthDate instanceof \DateTimeImmutable) {
            throw new BadRequestHttpException('Invalid birth date.');
        }

        $user = new User((string) $payload['email'], (string) $payload['username'], $birthDate);
        $user->setBio(isset($payload['bio']) ? (string) $payload['bio'] : null);
        $user->setPassword($passwordHasher->hashPassword($user, (string) $payload['password']));

        $userViolations = $validator->validate($user);
        if ($userViolations->count() > 0) {
            return $this->validationErrorResponse($userViolations);
        }

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json($this->userPayload($user), JsonResponse::HTTP_CREATED);
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user instanceof User) {
            return $this->json(['message' => 'Authentication required.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        return $this->json($this->userPayload($user));
    }

    #[Route('/api/me', name: 'api_me_update', methods: ['PATCH'])]
    public function updateMe(
        Request $request,
        #[CurrentUser] ?User $user,
        ValidatorInterface $validator,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        if (!$user instanceof User) {
            return $this->json(['message' => 'Authentication required.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $payload = $this->decodeJson($request);

        $violations = $validator->validate($payload, new Assert\Collection(
            fields: [
                'username' => new Assert\Optional([
                    new Assert\NotBlank(),
                    new Assert\Length(min: 3, max: 50),
                    new Assert\Regex(pattern: '/^[a-zA-Z0-9_]+$/'),
                ]),
                'birthDate' => new Assert\Optional([new Assert\NotBlank(), new Assert\Date()]),
                'bio' => new Assert\Optional([new Assert\Length(max: 1000)]),
                'avatarPath' => new Assert\Optional([new Assert\Length(max: 255)]),
            ],
            allowExtraFields: false,
            allowMissingFields: true,
        ));

        if ($violations->count() > 0) {
            return $this->validationErrorResponse($violations);
        }

        if (array_key_exists('username', $payload)) {
            $user->setUsername((string) $payload['username']);
        }

        if (array_key_exists('birthDate', $payload)) {
            $birthDate = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $payload['birthDate']);
            if (!$birthDate instanceof \DateTimeImmutable) {
                throw new BadRequestHttpException('Invalid birth date.');
            }

            $user->setBirthDate($birthDate);
        }

        if (array_key_exists('bio', $payload)) {
            $user->setBio(null === $payload['bio'] ? null : (string) $payload['bio']);
        }

        if (array_key_exists('avatarPath', $payload)) {
            $user->setAvatarPath(null === $payload['avatarPath'] ? null : (string) $payload['avatarPath']);
        }

        $userViolations = $validator->validate($user);
        if ($userViolations->count() > 0) {
            return $this->validationErrorResponse($userViolations);
        }

        $entityManager->flush();

        return $this->json($this->userPayload($user));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(Request $request): array
    {
        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new BadRequestHttpException('Invalid JSON body.');
        }

        if (!is_array($payload)) {
            throw new BadRequestHttpException('Expected a JSON object.');
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'username' => $user->getUsername(),
            'birthDate' => $user->getBirthDate()->format('Y-m-d'),
            'bio' => $user->getBio(),
            'avatarPath' => $user->getAvatarPath(),
            'roles' => $user->getRoles(),
            'createdAt' => $user->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $user->getUpdatedAt()->format(DATE_ATOM),
        ];
    }

    private function validationErrorResponse(ConstraintViolationListInterface $violations): JsonResponse
    {
        $errors = [];

        foreach ($violations as $violation) {
            $errors[] = [
                'property' => $violation->getPropertyPath(),
                'message' => $violation->getMessage(),
            ];
        }

        return $this->json(['errors' => $errors], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }
}
