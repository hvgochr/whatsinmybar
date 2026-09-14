<?php

namespace App\Tests\Auth;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie as BrowserCookie;
use Symfony\Component\HttpFoundation\Cookie;
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
        ], ['HTTPS' => 'on']);

        self::assertResponseIsSuccessful();

        $loginPayload = $this->jsonResponse($client);
        self::assertArrayHasKey('token', $loginPayload);
        self::assertArrayNotHasKey('refresh_token', $loginPayload);

        $loginCookie = $this->refreshTokenCookie($client);
        self::assertTrue($loginCookie->isHttpOnly());
        self::assertTrue($loginCookie->isSecure());
        self::assertSame(Cookie::SAMESITE_STRICT, $loginCookie->getSameSite());
        self::assertSame('/', $loginCookie->getPath());
        $loginRefreshToken = $loginCookie->getValue();

        $client->request('GET', '/api/me', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$loginPayload['token'],
        ]);

        self::assertResponseIsSuccessful();

        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
        $profile = $this->jsonResponse($client);
        self::assertSame($email, $profile['email']);
        self::assertSame($username, $profile['username']);

        $client->jsonRequest('POST', '/api/auth/refresh', [], ['HTTPS' => 'on']);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $client->jsonRequest('POST', '/api/auth/refresh', [], $this->cookieRequestHeaders());

        self::assertResponseIsSuccessful();

        $refreshPayload = $this->jsonResponse($client);
        self::assertArrayHasKey('token', $refreshPayload);
        self::assertArrayNotHasKey('refresh_token', $refreshPayload);
        self::assertIsString($refreshPayload['token']);
        self::assertNotSame('', $refreshPayload['token']);

        $rotatedRefreshToken = $this->refreshTokenCookie($client)->getValue();
        self::assertNotSame($loginRefreshToken, $rotatedRefreshToken);

        $this->setRefreshTokenCookie($client, $loginRefreshToken);
        $client->jsonRequest('POST', '/api/auth/refresh', [], $this->cookieRequestHeaders());

        self::assertResponseIsSuccessful();
        self::assertSame($rotatedRefreshToken, $this->refreshTokenCookie($client)->getValue());

        static::getContainer()->get(Connection::class)->executeStatement(
            'UPDATE refresh_tokens SET rotation_grace_until = ? WHERE refresh_token = ?',
            [time() - 1, $rotatedRefreshToken],
        );
        $this->setRefreshTokenCookie($client, $loginRefreshToken);
        $client->jsonRequest('POST', '/api/auth/refresh', [], $this->cookieRequestHeaders());
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertSame([], $client->getResponse()->headers->getCookies());

        $this->setRefreshTokenCookie($client, $rotatedRefreshToken);
        $client->jsonRequest('POST', '/api/auth/logout', [], $this->cookieRequestHeaders());

        self::assertResponseIsSuccessful();
        self::assertNull($this->refreshTokenCookie($client)->getValue());

        $this->setRefreshTokenCookie($client, $rotatedRefreshToken);
        $client->jsonRequest('POST', '/api/auth/refresh', [], $this->cookieRequestHeaders());

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testPasswordChangeRevokesRefreshSessions(): void
    {
        $client = static::createClient();
        $suffix = bin2hex(random_bytes(6));
        $email = sprintf('password-%s@example.com', $suffix);
        $password = 'very-secure-password';

        $client->jsonRequest('POST', '/api/auth/register', [
            'email' => $email,
            'username' => sprintf('password_%s', $suffix),
            'password' => $password,
            'birthDate' => '1990-01-01',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $client->jsonRequest('POST', '/api/auth/login', [
            'email' => $email,
            'password' => $password,
        ], ['HTTPS' => 'on']);
        self::assertResponseIsSuccessful();

        $loginPayload = $this->jsonResponse($client);
        $refreshToken = $this->refreshTokenCookie($client)->getValue();

        $client->jsonRequest('PATCH', '/api/me/password', [
            'currentPassword' => $password,
            'newPassword' => 'new-very-secure-password',
        ], ['HTTP_AUTHORIZATION' => 'Bearer '.$loginPayload['token']]);
        self::assertResponseIsSuccessful();

        $this->setRefreshTokenCookie($client, $refreshToken);
        $client->jsonRequest('POST', '/api/auth/refresh', [], $this->cookieRequestHeaders());

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testLockTimeoutDoesNotConsumeOrClearRefreshCookie(): void
    {
        $client = static::createClient();
        $suffix = bin2hex(random_bytes(6));
        $credentials = ['email' => "lock-$suffix@example.com", 'password' => 'very-secure-password'];
        $client->jsonRequest('POST', '/api/auth/register', $credentials + [
            'username' => "lock_$suffix", 'birthDate' => '1990-01-01',
        ]);
        self::assertResponseStatusCodeSame(201);
        $client->jsonRequest('POST', '/api/auth/login', $credentials, ['HTTPS' => 'on']);
        $cookie = $this->refreshTokenCookie($client)->getValue();
        $connection = static::getContainer()->get(Connection::class);
        $blocker = DriverManager::getConnection($connection->getParams());
        $blocker->beginTransaction();
        try {
            $blocker->executeQuery('SELECT id FROM refresh_tokens WHERE refresh_token = ? FOR UPDATE', [$cookie]);
            $client->jsonRequest('POST', '/api/auth/refresh', [], $this->cookieRequestHeaders());
            self::assertResponseStatusCodeSame(503);
            self::assertResponseHeaderSame('Retry-After', '2');
            self::assertSame([], $client->getResponse()->headers->getCookies());
        } finally {
            $blocker->rollBack();
            $blocker->close();
        }
        $client->jsonRequest('POST', '/api/auth/refresh', [], $this->cookieRequestHeaders());
        self::assertResponseIsSuccessful();
        self::assertNotSame($cookie, $this->refreshTokenCookie($client)->getValue());
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
        self::assertSame('validation_failed', $payload['error']['code']);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $payload['error']['status']);
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

    private function refreshTokenCookie(KernelBrowser $client): Cookie
    {
        foreach ($client->getResponse()->headers->getCookies() as $cookie) {
            if ('refresh_token' === $cookie->getName()) {
                return $cookie;
            }
        }

        self::fail('The response did not contain the refresh-token cookie.');
    }

    private function setRefreshTokenCookie(KernelBrowser $client, string $token): void
    {
        $client->getCookieJar()->set(new BrowserCookie(
            'refresh_token',
            $token,
            null,
            '/',
            'localhost',
            true,
            true,
            false,
            Cookie::SAMESITE_STRICT,
        ));
    }

    /**
     * @return array<string, string>
     */
    private function cookieRequestHeaders(): array
    {
        return [
            'HTTPS' => 'on',
            'HTTP_X_CSRF_PROTECTION' => '1',
        ];
    }
}
