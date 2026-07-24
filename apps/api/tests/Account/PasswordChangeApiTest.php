<?php

namespace App\Tests\Account;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PasswordChangeApiTest extends WebTestCase
{
    public function testUserCanChangePasswordAndLoginWithNewPassword(): void
    {
        $client = static::createClient();
        $user = $this->createUser('very-secure-password');
        $token = $this->loginAsUser($client, $user, 'very-secure-password');

        $client->jsonRequest('PATCH', '/api/me/password', [
            'currentPassword' => 'very-secure-password',
            'newPassword' => 'new-very-secure-password',
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();
        self::assertTrue($this->jsonResponse($client)['changed']);

        $client->jsonRequest('POST', '/api/auth/login', [
            'email' => $user->getEmail(),
            'password' => 'very-secure-password',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $client->jsonRequest('POST', '/api/auth/login', [
            'email' => $user->getEmail(),
            'password' => 'new-very-secure-password',
        ]);

        self::assertResponseIsSuccessful();
    }

    public function testCurrentPasswordMustBeValid(): void
    {
        $client = static::createClient();
        $user = $this->createUser('very-secure-password');
        $token = $this->loginAsUser($client, $user, 'very-secure-password');

        $client->jsonRequest('PATCH', '/api/me/password', [
            'currentPassword' => 'wrong-current-password',
            'newPassword' => 'new-very-secure-password',
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $client->jsonRequest('POST', '/api/auth/login', [
            'email' => $user->getEmail(),
            'password' => 'very-secure-password',
        ]);

        self::assertResponseIsSuccessful();
    }

    public function testNewPasswordMustBeStrongEnough(): void
    {
        $client = static::createClient();
        $user = $this->createUser('very-secure-password');
        $token = $this->loginAsUser($client, $user, 'very-secure-password');

        $client->jsonRequest('PATCH', '/api/me/password', [
            'currentPassword' => 'very-secure-password',
            'newPassword' => 'short',
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $payload = $this->jsonResponse($client);
        self::assertArrayHasKey('errors', $payload);
        self::assertNotEmpty($payload['errors']);
    }

    public function testAnonymousUserCannotChangePassword(): void
    {
        $client = static::createClient();

        $client->jsonRequest('PATCH', '/api/me/password', [
            'currentPassword' => 'very-secure-password',
            'newPassword' => 'new-very-secure-password',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    private function loginAsUser(KernelBrowser $client, User $user, string $password): string
    {
        $client->jsonRequest('POST', '/api/auth/login', [
            'email' => $user->getEmail(),
            'password' => $password,
        ]);

        self::assertResponseIsSuccessful();

        $payload = $this->jsonResponse($client);
        self::assertIsString($payload['token']);

        return $payload['token'];
    }

    private function createUser(string $password): User
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $suffix = bin2hex(random_bytes(6));

        $user = new User(
            sprintf('password-change-%s@example.com', $suffix),
            sprintf('password_change_%s', $suffix),
            new \DateTimeImmutable('1990-01-01'),
        );
        $user->setPassword($passwordHasher->hashPassword($user, $password));

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function jsonResponse(KernelBrowser $client): array
    {
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($payload);

        return $payload;
    }
}
