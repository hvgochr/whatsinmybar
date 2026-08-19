<?php

namespace App\Tests\Auth;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class DeletedAccountAuthenticationTest extends WebTestCase
{
    public function testDeletedAccountCannotLoginUseExistingJwtOrRefreshAndCanLoginAfterRestoration(): void
    {
        $client = static::createClient();
        $password = 'very-secure-password';
        $user = $this->createUser($password);

        $client->jsonRequest('POST', '/api/auth/login', [
            'email' => $user->getEmail(),
            'password' => $password,
        ]);

        self::assertResponseIsSuccessful();

        $tokens = $this->jsonResponse($client);
        self::assertIsString($tokens['token']);
        self::assertIsString($tokens['refresh_token']);

        $user->setDeletedAt(new \DateTimeImmutable());
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $client->request('GET', '/api/me', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$tokens['token'],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertDeletionStateIsNotDisclosed($client);

        $client->jsonRequest('POST', '/api/auth/login', [
            'email' => $user->getEmail(),
            'password' => $password,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertDeletionStateIsNotDisclosed($client);

        $client->jsonRequest('POST', '/api/auth/refresh', [
            'refresh_token' => $tokens['refresh_token'],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertDeletionStateIsNotDisclosed($client);

        $restoredUser = static::getContainer()->get(UserRepository::class)->find($user->getId());
        self::assertInstanceOf(User::class, $restoredUser);
        $restoredUser->setDeletedAt(null);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $client->jsonRequest('POST', '/api/auth/login', [
            'email' => $user->getEmail(),
            'password' => $password,
        ]);

        self::assertResponseIsSuccessful();
        self::assertArrayHasKey('token', $this->jsonResponse($client));
    }

    private function createUser(string $password): User
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $suffix = bin2hex(random_bytes(6));

        $user = new User(
            sprintf('deleted-auth-%s@example.com', $suffix),
            sprintf('deleted_auth_%s', $suffix),
            new \DateTimeImmutable('1990-01-01'),
        );
        $user->setPassword($passwordHasher->hashPassword($user, $password));

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function assertDeletionStateIsNotDisclosed(KernelBrowser $client): void
    {
        self::assertStringNotContainsString('deleted', strtolower((string) $client->getResponse()->getContent()));
        self::assertStringNotContainsString('moderation', strtolower((string) $client->getResponse()->getContent()));
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonResponse(KernelBrowser $client): array
    {
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($payload);

        return $payload;
    }
}
