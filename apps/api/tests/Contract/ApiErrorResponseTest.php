<?php

namespace App\Tests\Contract;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ApiErrorResponseTest extends WebTestCase
{
    public function testBadRequestExceptionsUseStableErrorShape(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/register', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: '{bad json');

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $payload = $this->jsonResponse($client);
        self::assertSame([
            'error' => [
                'status' => 400,
                'code' => 'bad_request',
                'message' => 'Invalid JSON body.',
            ],
        ], $payload);
    }

    public function testNotFoundExceptionsUseStableErrorShape(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/users/missing_user');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $payload = $this->jsonResponse($client);
        self::assertSame([
            'error' => [
                'status' => 404,
                'code' => 'not_found',
                'message' => 'User profile not found.',
            ],
        ], $payload);
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
