<?php

namespace App\Tests\Taxonomy;

use App\Entity\Category;
use App\Entity\Ingredient;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class TaxonomyApiTest extends WebTestCase
{
    public function testSelectorsCanLoadMoreThanOnePageOfTaxonomy(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));
        $slugs = [];
        for ($index = 1; $index <= 65; ++$index) {
            foreach ([new Category(), new Ingredient()] as $item) {
                $item->setName('Pagination '.$suffix.' '.$index);
                $entityManager->persist($item);
            }
            $slugs[] = $item->getSlug();
        }
        $entityManager->flush();

        foreach (['categories', 'ingredients'] as $resource) {
            $client->request('GET', '/api/'.$resource, server: ['HTTP_ACCEPT' => 'application/json']);
            self::assertResponseIsSuccessful();
            self::assertCount(30, $this->jsonResponse($client));

            $client->request('GET', '/api/'.$resource.'?pagination=false', server: ['HTTP_ACCEPT' => 'application/json']);
            self::assertResponseIsSuccessful();
            $items = $this->jsonResponse($client);
            self::assertTrue(array_is_list($items));
            self::assertSame([], array_diff($slugs, array_column($items, 'slug')));
        }

        $client->request('GET', '/api/ingredients/'.$slugs[64]);
        self::assertResponseIsSuccessful();
        self::assertSame($slugs[64], $this->jsonResponse($client)['slug']);
    }

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
     * @return array<array-key, mixed>
     */
    private function jsonResponse(KernelBrowser $client): array
    {
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($payload);

        return $payload;
    }
}
