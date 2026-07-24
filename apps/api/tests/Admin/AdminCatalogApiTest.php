<?php

namespace App\Tests\Admin;

use App\Entity\Category;
use App\Entity\Ingredient;
use App\Entity\Recipe;
use App\Entity\User;
use App\Enum\RecipeStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminCatalogApiTest extends WebTestCase
{
    public function testAdminCanListUsersRecipesCategoriesAndIngredients(): void
    {
        $client = static::createClient();
        $this->clearContent();
        $adminToken = $this->loginAsUser($client, ['ROLE_ADMIN']);
        $user = $this->createUser();
        $category = $this->createCategory();
        $ingredient = $this->createIngredient();
        $recipe = $this->createRecipe($user);

        $client->request('GET', '/api/admin/users', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$adminToken,
        ]);

        self::assertResponseIsSuccessful();
        self::assertContains($user->getUsername(), array_column($this->jsonResponse($client)['items'], 'username'));

        $client->request('GET', '/api/admin/recipes', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$adminToken,
        ]);

        self::assertResponseIsSuccessful();
        self::assertContains($recipe->getSlug(), array_column($this->jsonResponse($client)['items'], 'slug'));

        $client->request('GET', '/api/admin/categories', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$adminToken,
        ]);

        self::assertResponseIsSuccessful();
        self::assertContains($category->getSlug(), array_column($this->jsonResponse($client)['items'], 'slug'));

        $client->request('GET', '/api/admin/ingredients', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$adminToken,
        ]);

        self::assertResponseIsSuccessful();
        self::assertContains($ingredient->getSlug(), array_column($this->jsonResponse($client)['items'], 'slug'));
    }

    public function testNonAdminCannotListAdminCatalogs(): void
    {
        $client = static::createClient();
        $token = $this->loginAsUser($client);

        foreach (['users', 'recipes', 'categories', 'ingredients'] as $resource) {
            $client->request('GET', '/api/admin/'.$resource, server: [
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ]);

            self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        }
    }

    /**
     * @param list<string> $roles
     */
    private function loginAsUser(KernelBrowser $client, array $roles = []): string
    {
        $password = 'very-secure-password';
        $user = $this->createUser($password, $roles);

        $client->jsonRequest('POST', '/api/auth/login', [
            'email' => $user->getEmail(),
            'password' => $password,
        ]);

        self::assertResponseIsSuccessful();

        $payload = $this->jsonResponse($client);
        self::assertIsString($payload['token']);

        return $payload['token'];
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(string $password = 'very-secure-password', array $roles = []): User
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $suffix = bin2hex(random_bytes(6));

        $user = new User(
            sprintf('admin-catalog-%s@example.com', $suffix),
            sprintf('admin_catalog_%s', $suffix),
            new \DateTimeImmutable('1990-01-01'),
        );
        $user->setRoles($roles);
        $user->setPassword($passwordHasher->hashPassword($user, $password));

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function createRecipe(User $author): Recipe
    {
        $recipe = new Recipe();
        $recipe->setAuthor($author);
        $recipe->setTitle('Admin Recipe '.bin2hex(random_bytes(4)));
        $recipe->setDescription('Recipe used for admin listing tests.');
        $recipe->setStatus(RecipeStatus::Published);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($recipe);
        $entityManager->flush();

        return $recipe;
    }

    private function createCategory(): Category
    {
        $category = new Category();
        $category->setName('Admin Category '.bin2hex(random_bytes(4)));

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($category);
        $entityManager->flush();

        return $category;
    }

    private function createIngredient(): Ingredient
    {
        $ingredient = new Ingredient();
        $ingredient->setName('Admin Ingredient '.bin2hex(random_bytes(4)));

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($ingredient);
        $entityManager->flush();

        return $ingredient;
    }

    private function clearContent(): void
    {
        $connection = static::getContainer()->get(EntityManagerInterface::class)->getConnection();

        foreach (['report', 'comment', 'favorite', 'recipe_ingredient', 'recipe_step', 'recipe_category', 'recipe', 'category', 'ingredient'] as $table) {
            $connection->executeStatement(sprintf('DELETE FROM %s', $table));
        }
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
