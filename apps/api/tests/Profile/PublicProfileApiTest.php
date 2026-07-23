<?php

namespace App\Tests\Profile;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PublicProfileApiTest extends WebTestCase
{
    public function testPublicProfileCanBeReadByUsernameWithoutSensitiveFields(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $user->setBio('Home bartender');
        $user->setAvatarPath('/uploads/avatars/profile.jpg');

        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $client->request('GET', '/api/users/'.$user->getUsername());

        self::assertResponseIsSuccessful();

        $payload = $this->jsonResponse($client);
        self::assertSame($user->getId(), $payload['id']);
        self::assertSame($user->getUsername(), $payload['username']);
        self::assertSame('Home bartender', $payload['bio']);
        self::assertSame('/uploads/avatars/profile.jpg', $payload['avatarPath']);
        self::assertArrayHasKey('createdAt', $payload);
        self::assertArrayNotHasKey('email', $payload);
        self::assertArrayNotHasKey('birthDate', $payload);
        self::assertArrayNotHasKey('roles', $payload);
    }

    public function testDeletedProfileIsNotPubliclyReadable(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $user->setDeletedAt(new \DateTimeImmutable());

        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $client->request('GET', '/api/users/'.$user->getUsername());

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function createUser(): User
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $suffix = bin2hex(random_bytes(6));

        $user = new User(
            sprintf('public-profile-%s@example.com', $suffix),
            sprintf('public_profile_%s', $suffix),
            new \DateTimeImmutable('1990-01-01'),
        );
        $user->setPassword($passwordHasher->hashPassword($user, 'very-secure-password'));

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
