<?php

namespace App\Tests\Admin;

use App\Entity\Ingredient;
use App\Entity\Recipe;
use App\Entity\RecipeIngredient;
use App\Entity\RefreshToken;
use App\Entity\User;
use App\Enum\RecipeStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminMutationApiTest extends WebTestCase
{
    public function testAdminCanUpdateUserRolesAndDeletionState(): void
    {
        $client = static::createClient();
        $adminToken = $this->loginAsUser($client, ['ROLE_ADMIN']);
        $user = $this->createUser();
        $userTokens = $this->loginExistingUser($client, $user);

        self::assertGreaterThan(0, $this->refreshTokenCount($user));

        $client->jsonRequest('PATCH', '/api/admin/users/'.$user->getId(), [
            'roles' => ['ROLE_ADMIN', 'ROLE_USER'],
            'deleted' => true,
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$adminToken,
        ]);

        self::assertResponseIsSuccessful();

        $payload = $this->jsonResponse($client);
        self::assertContains('ROLE_ADMIN', $payload['roles']);
        self::assertTrue($payload['deleted']);
        self::assertIsString($payload['deletedAt']);
        self::assertSame(0, $this->refreshTokenCount($user));

        $client->request('GET', '/api/me', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$userTokens['token'],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $client->jsonRequest('PATCH', '/api/admin/users/'.$user->getId(), [
            'deleted' => false,
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$adminToken,
        ]);

        self::assertResponseIsSuccessful();

        $payload = $this->jsonResponse($client);
        self::assertFalse($payload['deleted']);
        self::assertNull($payload['deletedAt']);

        $this->loginExistingUser($client, $user);
        self::assertResponseIsSuccessful();
    }

    public function testAdminCanModerateRecipe(): void
    {
        $client = static::createClient();
        $adminToken = $this->loginAsUser($client, ['ROLE_ADMIN']);
        $recipe = $this->createRecipe($this->createUser());

        $client->jsonRequest('PATCH', '/api/admin/recipes/'.$recipe->getSlug(), [
            'status' => 'archived',
            'moderationStatus' => 'hidden',
            'containsAlcoholOverride' => true,
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$adminToken,
        ]);

        self::assertResponseIsSuccessful();

        $payload = $this->jsonResponse($client);
        self::assertSame('archived', $payload['status']);
        self::assertSame('hidden', $payload['moderationStatus']);
        self::assertTrue($payload['containsAlcoholOverride']);

        foreach ([false, null] as $override) {
            $client->jsonRequest('PATCH', '/api/admin/recipes/'.$recipe->getSlug(), [
                'containsAlcoholOverride' => $override,
            ], server: [
                'HTTP_AUTHORIZATION' => 'Bearer '.$adminToken,
            ]);

            self::assertResponseIsSuccessful();
            self::assertSame($override, $this->jsonResponse($client)['containsAlcoholOverride']);
        }
    }

    public function testChangingIngredientAlcoholStatusRecalculatesEveryAffectedRecipe(): void
    {
        $client = static::createClient();
        $this->clearTaxonomy();
        $adminToken = $this->loginAsUser($client, ['ROLE_ADMIN']);
        $ingredient = $this->createIngredient(false);
        $recipes = [
            $this->createRecipeWithIngredient($this->createUser(), $ingredient),
            $this->createRecipeWithIngredient($this->createUser(), $ingredient),
        ];
        $alwaysAlcoholicIngredient = $this->createIngredient(true);
        $this->addIngredientToRecipe($recipes[0], $alwaysAlcoholicIngredient, 2);

        foreach ([true, false] as $index => $containsAlcohol) {
            $path = 0 === $index ? '/api/admin/ingredients/' : '/api/ingredients/';
            $client->jsonRequest('PATCH', $path.$ingredient->getSlug(), [
                'containsAlcohol' => $containsAlcohol,
            ], server: [
                'HTTP_AUTHORIZATION' => 'Bearer '.$adminToken,
                'CONTENT_TYPE' => 'application/merge-patch+json',
            ]);

            self::assertResponseIsSuccessful();

            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            foreach ($recipes as $recipeIndex => $recipe) {
                $updatedRecipe = $entityManager->find(Recipe::class, $recipe->getId());
                self::assertInstanceOf(Recipe::class, $updatedRecipe);
                self::assertSame($containsAlcohol || 0 === $recipeIndex, $updatedRecipe->containsAlcoholComputed());
            }
        }
    }

    public function testAdminCanCreateAndUpdateCategoryAndIngredient(): void
    {
        $client = static::createClient();
        $this->clearTaxonomy();
        $adminToken = $this->loginAsUser($client, ['ROLE_ADMIN']);

        $client->jsonRequest('POST', '/api/admin/categories', [
            'name' => 'Admin Classics',
            'description' => 'Classic cocktails.',
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$adminToken,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $category = $this->jsonResponse($client);
        self::assertSame('admin-classics', $category['slug']);

        $client->jsonRequest('PATCH', '/api/admin/categories/'.$category['slug'], [
            'description' => 'Updated classics.',
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$adminToken,
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame('Updated classics.', $this->jsonResponse($client)['description']);

        $client->jsonRequest('POST', '/api/admin/ingredients', [
            'name' => 'Admin Gin',
            'containsAlcohol' => true,
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$adminToken,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $ingredient = $this->jsonResponse($client);
        self::assertSame('admin-gin', $ingredient['slug']);
        self::assertTrue($ingredient['containsAlcohol']);

        $client->jsonRequest('PATCH', '/api/admin/ingredients/'.$ingredient['slug'], [
            'containsAlcohol' => false,
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$adminToken,
        ]);

        self::assertResponseIsSuccessful();
        self::assertFalse($this->jsonResponse($client)['containsAlcohol']);
    }

    public function testNonAdminCannotMutateAdminResources(): void
    {
        $client = static::createClient();
        $token = $this->loginAsUser($client);
        $user = $this->createUser();

        $client->jsonRequest('PATCH', '/api/admin/users/'.$user->getId(), [
            'deleted' => true,
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testLastActiveAdminCannotBeDeletedOrDemoted(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        foreach ($entityManager->getRepository(User::class)->findAll() as $existingUser) {
            $existingUser->setRoles([]);
        }
        $entityManager->flush();

        $primary = $this->createUser(roles: ['ROLE_ADMIN']);
        $secondary = $this->createUser(roles: ['ROLE_ADMIN']);
        $primaryToken = $this->loginExistingUser($client, $primary)['token'];

        $client->jsonRequest('PATCH', '/api/admin/users/'.$secondary->getId(), [
            'roles' => [],
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$primaryToken,
        ]);
        self::assertResponseIsSuccessful();

        foreach ([['deleted' => true], ['roles' => []]] as $payload) {
            $client->jsonRequest('PATCH', '/api/admin/users/'.$primary->getId(), $payload, server: [
                'HTTP_AUTHORIZATION' => 'Bearer '.$primaryToken,
            ]);

            self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
            self::assertSame('conflict', $this->jsonResponse($client)['error']['code']);
        }

        $entityManager->clear();
        $storedPrimary = $entityManager->find(User::class, $primary->getId());
        self::assertInstanceOf(User::class, $storedPrimary);
        self::assertNull($storedPrimary->getDeletedAt());
        self::assertContains('ROLE_ADMIN', $storedPrimary->getRoles());
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
     * @return array{token: string}
     */
    private function loginExistingUser(KernelBrowser $client, User $user): array
    {
        $client->jsonRequest('POST', '/api/auth/login', [
            'email' => $user->getEmail(),
            'password' => 'very-secure-password',
        ]);

        self::assertResponseIsSuccessful();

        $payload = $this->jsonResponse($client);
        self::assertIsString($payload['token']);
        self::assertArrayNotHasKey('refresh_token', $payload);

        return $payload;
    }

    private function refreshTokenCount(User $user): int
    {
        return static::getContainer()->get(EntityManagerInterface::class)->getRepository(RefreshToken::class)->count([
            'username' => $user->getUserIdentifier(),
        ]);
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
            sprintf('admin-mutation-%s@example.com', $suffix),
            sprintf('admin_mutation_%s', $suffix),
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
        $recipe->setTitle('Admin Mutation Recipe '.bin2hex(random_bytes(4)));
        $recipe->setDescription('Recipe used for admin mutation tests.');
        $recipe->setStatus(RecipeStatus::Published);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($recipe);
        $entityManager->flush();

        return $recipe;
    }

    private function createIngredient(bool $containsAlcohol): Ingredient
    {
        $ingredient = new Ingredient();
        $ingredient->setName('Shared Ingredient '.bin2hex(random_bytes(4)));
        $ingredient->setContainsAlcohol($containsAlcohol);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($ingredient);
        $entityManager->flush();

        return $ingredient;
    }

    private function createRecipeWithIngredient(User $author, Ingredient $ingredient): Recipe
    {
        $recipe = $this->createRecipe($author);
        $this->addIngredientToRecipe($recipe, $ingredient, 1);

        return $recipe;
    }

    private function addIngredientToRecipe(Recipe $recipe, Ingredient $ingredient, int $position): void
    {
        $recipeIngredient = new RecipeIngredient();
        $recipeIngredient->setIngredient($ingredient);
        $recipeIngredient->setPosition($position);
        $recipe->addRecipeIngredient($recipeIngredient);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($recipeIngredient);
        $entityManager->flush();
    }

    private function clearTaxonomy(): void
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
