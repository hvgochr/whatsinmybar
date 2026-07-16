<?php

namespace App\Tests\Auth;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class AuthControllerTest extends WebTestCase
{
    public function testUserCanRegisterLoginReadProfileAndRefreshToken(): void
    {
        $client = static::createClient();
        $suffix = bin2hex(random_bytes(6));
        $email = sprintf('user-%s@example.com', $suffix);
        $username = sprintf('user_%s', $suffix);
        $password = 'very-secure-password';

        $client->jsonRequest('POST', '/api/auth/register', [
            'email' => $email,
            'username' => $username,
            'password' => $password,
            'birthDate' => '1990-01-01',
            'bio' => 'Home bartender',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $registeredUser = $this->jsonResponse($client);
        self::assertSame($email, $registeredUser['email']);
        self::assertSame($username, $registeredUser['username']);
        self::assertArrayNotHasKey('password', $registeredUser);

        $client->jsonRequest('POST', '/api/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);

        self::assertResponseIsSuccessful();

        $loginPayload = $this->jsonResponse($client);
        self::assertArrayHasKey('token', $loginPayload);
        self::assertArrayHasKey('refresh_token', $loginPayload);

        $client->request('GET', '/api/me', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$loginPayload['token'],
        ]);

        self::assertResponseIsSuccessful();

        $profile = $this->jsonResponse($client);
        self::assertSame($email, $profile['email']);
        self::assertSame($username, $profile['username']);

        $client->jsonRequest('POST', '/api/auth/refresh', [
            'refresh_token' => $loginPayload['refresh_token'],
        ]);

        self::assertResponseIsSuccessful();

        $refreshPayload = $this->jsonResponse($client);
        self::assertArrayHasKey('token', $refreshPayload);
        self::assertArrayHasKey('refresh_token', $refreshPayload);
        self::assertIsString($refreshPayload['token']);
        self::assertNotSame('', $refreshPayload['token']);
    }

    public function testRegistrationValidationErrorsAreReturned(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/auth/register', [
            'email' => 'not-an-email',
            'username' => 'no',
            'password' => 'short',
            'birthDate' => '',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $payload = $this->jsonResponse($client);
        self::assertArrayHasKey('errors', $payload);
        self::assertNotEmpty($payload['errors']);
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
