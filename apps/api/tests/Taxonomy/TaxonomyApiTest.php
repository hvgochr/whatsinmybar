<?php

namespace App\Tests\Taxonomy;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class TaxonomyApiTest extends WebTestCase
{
    public function testAdminCanCreateIngredientAndPublicCanReadItBySlug(): void
    {
        $client = static::createClient();
        $token = $this->loginAsAdmin($client);
        $suffix = bin2hex(random_bytes(4));
        $name = sprintf('Dry Gin %s', $suffix);
        $slug = sprintf('dry-gin-%s', $suffix);

        $client->jsonRequest('POST', '/api/ingredients', [
            'name' => $name,
            'containsAlcohol' => true,
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $ingredient = $this->jsonResponse($client);
        self::assertSame($name, $ingredient['name']);
        self::assertSame($slug, $ingredient['slug']);
        self::assertArrayHasKey('containsAlcohol', $ingredient);
        self::assertTrue($ingredient['containsAlcohol']);

        $client->request('GET', '/api/ingredients/'.$slug);

        self::assertResponseIsSuccessful();

        $ingredient = $this->jsonResponse($client);
        self::assertSame($slug, $ingredient['slug']);
    }

    public function testAdminCanCreateCategoryAndPublicCanReadItBySlug(): void
    {
        $client = static::createClient();
        $token = $this->loginAsAdmin($client);
        $suffix = bin2hex(random_bytes(4));
        $name = sprintf('Classic Cocktails %s', $suffix);
        $slug = sprintf('classic-cocktails-%s', $suffix);

        $client->jsonRequest('POST', '/api/categories', [
            'name' => $name,
            'description' => 'Canonical cocktails every home bartender should know.',
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $category = $this->jsonResponse($client);
        self::assertSame($name, $category['name']);
        self::assertSame($slug, $category['slug']);
        self::assertSame('Canonical cocktails every home bartender should know.', $category['description']);

        $client->request('GET', '/api/categories/'.$slug);

        self::assertResponseIsSuccessful();

        $category = $this->jsonResponse($client);
        self::assertSame($slug, $category['slug']);
    }

    private function loginAsAdmin(KernelBrowser $client): string
    {
        $password = 'very-secure-password';
        $admin = $this->createAdminUser($password);

        $client->jsonRequest('POST', '/api/auth/login', [
            'email' => $admin->getEmail(),
            'password' => $password,
        ]);

        self::assertResponseIsSuccessful();

        $payload = $this->jsonResponse($client);
        self::assertIsString($payload['token']);

        return $payload['token'];
    }

    private function createAdminUser(string $password): User
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $suffix = bin2hex(random_bytes(6));

        $admin = new User(
            sprintf('admin-%s@example.com', $suffix),
            sprintf('admin_%s', $suffix),
            new \DateTimeImmutable('1990-01-01'),
        );
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($passwordHasher->hashPassword($admin, $password));

        $entityManager->persist($admin);
        $entityManager->flush();

        return $admin;
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
