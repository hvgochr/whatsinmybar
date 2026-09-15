<?php

namespace App\Tests\Profile;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class CurrentProfileApiTest extends WebTestCase
{
    public function testUserCanUpdateOwnProfile(): void
    {
        $client = static::createClient();
        $token = $this->loginAsUser($client);
        $newUsername = 'updated_profile_'.bin2hex(random_bytes(4));

        $client->jsonRequest('PATCH', '/api/me', [
            'username' => $newUsername,
            'birthDate' => '1988-05-20',
            'bio' => ' Updated bio ',
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();

        $payload = $this->jsonResponse($client);
        self::assertSame($newUsername, $payload['username']);
        self::assertSame('1988-05-20', $payload['birthDate']);
        self::assertSame('Updated bio', $payload['bio']);
        self::assertNull($payload['avatarPath']);

        $client->request('GET', '/api/users/'.$newUsername);

        self::assertResponseIsSuccessful();

        $publicPayload = $this->jsonResponse($client);
        self::assertSame($newUsername, $publicPayload['username']);
        self::assertSame('Updated bio', $publicPayload['bio']);
    }

    public function testProfileUpdateRejectsDirectAvatarPathWrites(): void
    {
        $client = static::createClient();
        $token = $this->loginAsUser($client);

        $client->jsonRequest('PATCH', '/api/me', [
            'avatarPath' => '/uploads/avatars/manual.jpg',
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testProfileUpdateRejectsFutureAndSilentlyNormalizedBirthDates(): void
    {
        $client = static::createClient();
        $token = $this->loginAsUser($client);

        foreach (['2999-01-01', '2026-02-30'] as $birthDate) {
            $client->jsonRequest('PATCH', '/api/me', [
                'birthDate' => $birthDate,
            ], server: [
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ]);

            self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function testProfileUpdateValidatesUniqueUsername(): void
    {
        $client = static::createClient();
        $reservedUser = $this->createUser('very-secure-password');
        $token = $this->loginAsUser($client);

        $client->jsonRequest('PATCH', '/api/me', [
            'username' => $reservedUser->getUsername(),
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $payload = $this->jsonResponse($client);
        self::assertArrayHasKey('errors', $payload);
        self::assertNotEmpty($payload['errors']);
    }

    public function testAnonymousUserCannotUpdateProfile(): void
    {
        $client = static::createClient();

        $client->jsonRequest('PATCH', '/api/me', [
            'bio' => 'Nope',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testUserCanUploadAvatar(): void
    {
        $client = static::createClient();
        $token = $this->loginAsUser($client);
        $avatar = $this->pngUpload();

        $client->request('POST', '/api/me/avatar', files: [
            'avatar' => $avatar,
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();

        $payload = $this->jsonResponse($client);
        self::assertIsString($payload['avatarPath']);
        self::assertMatchesRegularExpression('#^/uploads/avatars/[a-f0-9]{32}\.png$#', $payload['avatarPath']);
    }

    public function testAvatarUploadRejectsUnsupportedFiles(): void
    {
        $client = static::createClient();
        $token = $this->loginAsUser($client);
        $filePath = tempnam(sys_get_temp_dir(), 'avatar-upload');
        self::assertIsString($filePath);
        file_put_contents($filePath, 'not an image');

        $client->request('POST', '/api/me/avatar', files: [
            'avatar' => new UploadedFile($filePath, 'avatar.txt', 'text/plain', test: true),
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    private function loginAsUser(KernelBrowser $client): string
    {
        $password = 'very-secure-password';
        $user = $this->createUser($password);

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
            sprintf('current-profile-%s@example.com', $suffix),
            sprintf('current_profile_%s', $suffix),
            new \DateTimeImmutable('1990-01-01'),
        );
        $user->setPassword($passwordHasher->hashPassword($user, $password));

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function pngUpload(): UploadedFile
    {
        $filePath = tempnam(sys_get_temp_dir(), 'avatar-upload');
        self::assertIsString($filePath);

        imagepng(imagecreatetruecolor(2, 2), $filePath);

        return new UploadedFile($filePath, 'avatar.png', 'image/png', test: true);
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
