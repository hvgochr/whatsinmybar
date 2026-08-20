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
        $recipe->setContainsAlcoholOverride(false);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $client->request('GET', '/api/admin/users', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$adminToken,
        ]);

        self::assertResponseIsSuccessful();
        $userPage = $this->jsonResponse($client);
        $this->assertDefaultPaginationMetadata($userPage);
        self::assertContains($user->getUsername(), array_column($userPage['items'], 'username'));

        $client->request('GET', '/api/admin/recipes', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$adminToken,
        ]);

        self::assertResponseIsSuccessful();
        $recipePage = $this->jsonResponse($client);
        $this->assertDefaultPaginationMetadata($recipePage);
        $recipeRows = $recipePage['items'];
        self::assertContains($recipe->getSlug(), array_column($recipeRows, 'slug'));
        $matchingRecipeRows = array_values(array_filter(
            $recipeRows,
            static fn (mixed $row): bool => is_array($row) && ($row['slug'] ?? null) === $recipe->getSlug(),
        ));
        self::assertCount(1, $matchingRecipeRows);
        $recipeRow = $matchingRecipeRows[0];
        self::assertIsArray($recipeRow);
        self::assertFalse($recipeRow['containsAlcoholOverride']);

        $client->request('GET', '/api/admin/categories', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$adminToken,
        ]);

        self::assertResponseIsSuccessful();
        $categoryPage = $this->jsonResponse($client);
        $this->assertDefaultPaginationMetadata($categoryPage);
        self::assertContains($category->getSlug(), array_column($categoryPage['items'], 'slug'));

        $client->request('GET', '/api/admin/ingredients', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$adminToken,
        ]);

        self::assertResponseIsSuccessful();
        $ingredientPage = $this->jsonResponse($client);
        $this->assertDefaultPaginationMetadata($ingredientPage);
        self::assertContains($ingredient->getSlug(), array_column($ingredientPage['items'], 'slug'));
    }

    public function testAdminCatalogPaginationIsBoundedDeterministicAndHandlesOutOfRangePages(): void
    {
        $client = static::createClient();
        $this->clearContent();
        $adminToken = $this->loginAsUser($client, ['ROLE_ADMIN']);

        for ($index = 1; $index <= 21; ++$index) {
            $this->createCategory(sprintf('Paginated Category %02d', $index));
        }

        $client->request('GET', '/api/admin/categories', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$adminToken,
        ]);

        self::assertResponseIsSuccessful();
        $defaultPage = $this->jsonResponse($client);
        self::assertCount(20, $defaultPage['items']);
        self::assertSame(20, $defaultPage['pageSize']);
        self::assertSame(21, $defaultPage['totalItems']);
        self::assertSame(2, $defaultPage['totalPages']);

        $client->request('GET', '/api/admin/categories?page=1&pageSize=5', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$adminToken,
        ]);

        self::assertResponseIsSuccessful();
        $firstPage = $this->jsonResponse($client);
        self::assertCount(5, $firstPage['items']);
        self::assertSame(1, $firstPage['page']);
        self::assertSame(5, $firstPage['pageSize']);
        self::assertSame(21, $firstPage['totalItems']);
        self::assertSame(5, $firstPage['totalPages']);
        $firstPageIds = array_column($firstPage['items'], 'id');
        $sortedFirstPageIds = $firstPageIds;
        rsort($sortedFirstPageIds);
        self::assertSame($sortedFirstPageIds, $firstPageIds);

        $client->request('GET', '/api/admin/categories?page=2&pageSize=5', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$adminToken,
        ]);

        self::assertResponseIsSuccessful();
        $secondPage = $this->jsonResponse($client);
        self::assertCount(5, $secondPage['items']);
        self::assertSame([], array_intersect($firstPageIds, array_column($secondPage['items'], 'id')));

        $client->request('GET', '/api/admin/categories?page=6&pageSize=5', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$adminToken,
        ]);

        self::assertResponseIsSuccessful();
        $outOfRangePage = $this->jsonResponse($client);
        self::assertSame([], $outOfRangePage['items']);
        self::assertSame(6, $outOfRangePage['page']);
        self::assertSame(21, $outOfRangePage['totalItems']);
        self::assertSame(5, $outOfRangePage['totalPages']);
    }

    public function testAdminCatalogsRejectInvalidPaginationParameters(): void
    {
        $client = static::createClient();
        $adminToken = $this->loginAsUser($client, ['ROLE_ADMIN']);

        foreach (['users', 'recipes', 'categories', 'ingredients'] as $resource) {
            $client->request('GET', '/api/admin/'.$resource.'?page=0', server: [
                'HTTP_AUTHORIZATION' => 'Bearer '.$adminToken,
            ]);

            self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
            self::assertSame('page must be a positive integer.', $this->jsonResponse($client)['error']['message']);

            $client->request('GET', '/api/admin/'.$resource.'?pageSize=101', server: [
                'HTTP_AUTHORIZATION' => 'Bearer '.$adminToken,
            ]);

            self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
            self::assertSame('pageSize must be between 1 and 100.', $this->jsonResponse($client)['error']['message']);
        }
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

    private function createCategory(?string $name = null): Category
    {
        $category = new Category();
        $category->setName($name ?? 'Admin Category '.bin2hex(random_bytes(4)));

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
     * @param array<array-key, mixed> $payload
     */
    private function assertDefaultPaginationMetadata(array $payload): void
    {
        self::assertSame(1, $payload['page']);
        self::assertSame(20, $payload['pageSize']);
        self::assertIsInt($payload['totalItems']);
        self::assertIsInt($payload['totalPages']);
        self::assertLessThanOrEqual(20, count($payload['items']));
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
