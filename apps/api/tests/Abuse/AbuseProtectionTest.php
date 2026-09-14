<?php

namespace App\Tests\Abuse;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class AbuseProtectionTest extends WebTestCase
{
    public function testLoginCountsNormalizedAccountAndIpBeforeAuthentication(): void
    {
        $client = self::createClient();
        for ($i = 0; $i < 8; ++$i) {
            $client->jsonRequest('POST', '/api/auth/login', ['email' => 'nobody@example.com', 'password' => 'wrong']);
            self::assertResponseStatusCodeSame(401);
        }
        $client->jsonRequest('POST', '/api/auth/login', ['email' => ' NOBODY@EXAMPLE.COM ', 'password' => 'wrong']);
        $this->assertLimited();
        $client->jsonRequest('POST', '/api/auth/login', ['email' => 'another@example.com', 'password' => 'wrong']);
        self::assertResponseStatusCodeSame(401);
        $client->jsonRequest('POST', '/api/auth/login', ['email' => 'nobody@example.com', 'password' => 'wrong'], ['REMOTE_ADDR' => '192.0.2.2']);
        self::assertResponseStatusCodeSame(401);
    }

    public function testChangingAccountsDoesNotBypassIpLimit(): void
    {
        $client = self::createClient();
        for ($i = 0; $i < 40; ++$i) {
            $client->jsonRequest('POST', '/api/auth/login', ['email' => "missing$i@example.com", 'password' => 'wrong']);
            self::assertResponseStatusCodeSame(401);
        }
        $client->jsonRequest('POST', '/api/auth/login', ['email' => 'fresh@example.com', 'password' => 'wrong']);
        $this->assertLimited();
    }

    public function testRegistrationRejectsSpoofingAndDoesNotLimitReadsOrRefresh(): void
    {
        $client = self::createClient();
        for ($i = 0; $i < 5; ++$i) {
            $client->jsonRequest('POST', '/api/auth/register', [], ['REMOTE_ADDR' => '192.0.2.10', 'HTTP_X_FORWARDED_FOR' => "198.51.100.$i"]);
            self::assertResponseStatusCodeSame(422);
        }
        $client->jsonRequest('POST', '/api/auth/register', [], ['REMOTE_ADDR' => '192.0.2.10', 'HTTP_FORWARDED' => 'for=203.0.113.2', 'HTTP_X_REAL_IP' => '203.0.113.3']);
        $this->assertLimited();
        $client->jsonRequest('POST', '/api/auth/%72egister', [], ['REMOTE_ADDR' => '192.0.2.10']);
        $this->assertLimited();
        $client->jsonRequest('POST', '/api/auth/register', [], ['REMOTE_ADDR' => '192.0.2.11']);
        self::assertResponseStatusCodeSame(422);
        for ($i = 0; $i < 6; ++$i) {
            $client->request('GET', '/api/categories', server: ['REMOTE_ADDR' => '192.0.2.10']);
            self::assertResponseIsSuccessful();
            $client->jsonRequest('POST', '/api/auth/refresh', [], ['REMOTE_ADDR' => '192.0.2.10', 'HTTP_X_CSRF_PROTECTION' => '1']);
            self::assertResponseStatusCodeSame(401);
        }
    }

    public function testTrustedProxyExtractsDistinctVisitorsAndIgnoresOtherHeaders(): void
    {
        $client = self::createClient();
        $proxy = explode(',', (string) ($_SERVER['SYMFONY_TRUSTED_PROXIES'] ?? $_ENV['SYMFONY_TRUSTED_PROXIES'] ?? '172.30.71.2'))[0];
        for ($i = 0; $i < 5; ++$i) {
            $client->jsonRequest('POST', '/api/auth/register', [], ['REMOTE_ADDR' => $proxy, 'HTTP_X_FORWARDED_FOR' => '192.0.2.20']);
            self::assertResponseStatusCodeSame(422);
        }
        $client->jsonRequest('POST', '/api/auth/register', [], ['REMOTE_ADDR' => $proxy, 'HTTP_X_FORWARDED_FOR' => '192.0.2.20', 'HTTP_FORWARDED' => 'for=203.0.113.5']);
        $this->assertLimited();
        $client->jsonRequest('POST', '/api/auth/register', [], ['REMOTE_ADDR' => $proxy, 'HTTP_X_FORWARDED_FOR' => '192.0.2.21']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO, Request::getTrustedHeaderSet());
    }

    public function testEquivalentWritesShareBudgetsAndUsersAreIsolated(): void
    {
        $client = self::createClient();
        $token = $this->token();
        $otherToken = $this->token();
        foreach ([
            [60, [['POST', '/api/recipes'], ['POST', '/api/recipes.json'], ['POST', '/api/recipes.jsonld'], ['POST', '/api/%72ecipes'], ['POST', '/api/recipes/aggregate'], ['PUT', '/api/recipes/missing/aggregate'], ['PATCH', '/api/recipes/missing'], ['POST', '/api/recipe_steps.json'], ['POST', '/api/recipe_ingredients.jsonld'], ['DELETE', '/api/recipe_steps/123'], ['PATCH', '/api/admin/recipes/missing']]],
            [20, [['POST', '/api/recipes/missing/comments'], ['PATCH', '/api/comments/123'], ['DELETE', '/api/comments/123']]],
            [5, [['POST', '/api/reports']]],
            [10, [['POST', '/api/me/avatar'], ['POST', '/api/recipes/missing/image'], ['DELETE', '/api/recipes/missing/image']]],
        ] as [$limit, $routes]) {
            for ($i = 0; $i < $limit; ++$i) {
                [$method, $path] = $routes[$i % count($routes)];
                $client->jsonRequest($method, $path, [], ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
                self::assertNotSame(429, $client->getResponse()->getStatusCode(), "$method $path");
            }
            foreach ($routes as [$method, $path]) {
                $client->jsonRequest($method, $path, [], ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'REMOTE_ADDR' => '192.0.2.99']);
                $this->assertLimited();
                $client->jsonRequest($method, $path, [], ['HTTP_AUTHORIZATION' => 'Bearer '.$otherToken]);
                self::assertNotSame(429, $client->getResponse()->getStatusCode());
            }
        }
    }

    private function token(): string
    {
        $suffix = bin2hex(random_bytes(6));
        $user = new User("abuse-$suffix@example.com", "abuse_$suffix", new \DateTimeImmutable('1990-01-01'));
        $user->setPassword('unused');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($user);
        $em->flush();

        return self::getContainer()->get(JWTTokenManagerInterface::class)->create($user);
    }

    private function assertLimited(): void
    {
        self::assertResponseStatusCodeSame(429);
        $response = self::getClient()->getResponse();
        self::assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
        self::assertSame(['error' => ['status' => 429, 'code' => 'too_many_requests', 'message' => 'Too many requests. Please try again later.']], json_decode((string) $response->getContent(), true));
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
    }
}
