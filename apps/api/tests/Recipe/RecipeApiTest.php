<?php

namespace App\Tests\Recipe;

use App\Entity\Category;
use App\Entity\Ingredient;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class RecipeApiTest extends WebTestCase
{
    public function testAuthenticatedUserCanCreateAndReadOwnDraftRecipe(): void
    {
        $client = static::createClient();
        $token = $this->loginAsUser($client);
        $category = $this->createCategory();
        $suffix = bin2hex(random_bytes(4));
        $slug = sprintf('draft-martini-%s', $suffix);

        $client->jsonRequest('POST', '/api/recipes', [
            'title' => sprintf('Draft Martini %s', $suffix),
            'description' => 'A private work in progress.',
            'difficulty' => 'medium',
            'preparationTimeMinutes' => 5,
            'servings' => 1,
            'status' => 'draft',
            'imagePath' => '/uploads/recipes/manual.jpg',
            'categories' => ['/api/categories/'.$category->getSlug()],
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $recipe = $this->jsonResponse($client);
        self::assertSame($slug, $recipe['slug']);
        self::assertSame('medium', $recipe['difficulty']);
        self::assertSame('draft', $recipe['status']);
        self::assertNull($recipe['imagePath']);
        self::assertNotEmpty($recipe['authorUsername']);

        $client->request('GET', '/api/recipes/'.$slug);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $client->request('GET', '/api/recipes/'.$slug, server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();
    }

    public function testAuthorCannotSetOrChangeAlcoholOverride(): void
    {
        $client = static::createClient();
        $token = $this->loginAsUser($client);
        $suffix = bin2hex(random_bytes(4));
        $slug = sprintf('protected-override-%s', $suffix);

        $client->jsonRequest('POST', '/api/recipes', [
            'title' => sprintf('Protected Override %s', $suffix),
            'description' => 'A recipe whose classification is controlled by administrators.',
            'containsAlcoholOverride' => true,
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertNull($this->jsonResponse($client)['containsAlcoholOverride']);

        $recipe = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(\App\Entity\Recipe::class)
            ->findOneBy(['slug' => $slug]);
        self::assertInstanceOf(\App\Entity\Recipe::class, $recipe);
        $recipe->setContainsAlcoholOverride(true);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $client->jsonRequest('PATCH', '/api/recipes/'.$slug, [
            'containsAlcoholOverride' => false,
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
        ]);

        self::assertResponseIsSuccessful();
        self::assertTrue($this->jsonResponse($client)['containsAlcoholOverride']);
    }

    public function testPublishedRecipeIsVisibleInPublicCollection(): void
    {
        $client = static::createClient();
        $this->clearRecipes();
        $token = $this->loginAsUser($client);
        $suffix = bin2hex(random_bytes(4));
        $slug = sprintf('public-negroni-%s', $suffix);

        $client->jsonRequest('POST', '/api/recipes', [
            'title' => sprintf('Public Negroni %s', $suffix),
            'description' => 'A published cocktail.',
            'difficulty' => 'easy',
            'preparationTimeMinutes' => 3,
            'servings' => 1,
            'status' => 'published',
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $recipe = $this->jsonResponse($client);
        self::assertSame($slug, $recipe['slug']);
        self::assertSame('published', $recipe['status']);
        self::assertNotNull($recipe['publishedAt']);

        $client->request('GET', '/api/recipes?pagination=false');

        self::assertResponseIsSuccessful();

        $slugs = array_map(
            static fn (array $recipe): string => (string) $recipe['slug'],
            $this->collectionItems($client),
        );
        self::assertContains($slug, $slugs);
    }

    public function testAuthorCanAddStepsAndMeasuredIngredients(): void
    {
        $client = static::createClient();
        $token = $this->loginAsUser($client);
        $ingredient = $this->createIngredient(containsAlcohol: true);
        $suffix = bin2hex(random_bytes(4));
        $slug = sprintf('measured-sour-%s', $suffix);

        $client->jsonRequest('POST', '/api/recipes', [
            'title' => sprintf('Measured Sour %s', $suffix),
            'description' => 'A recipe with ordered parts.',
            'difficulty' => 'hard',
            'preparationTimeMinutes' => 8,
            'servings' => 1,
            'status' => 'draft',
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $client->jsonRequest('POST', '/api/recipe_steps', [
            'recipe' => '/api/recipes/'.$slug,
            'position' => 1,
            'instruction' => 'Shake all ingredients with ice.',
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $client->jsonRequest('POST', '/api/recipe_ingredients', [
            'recipe' => '/api/recipes/'.$slug,
            'ingredient' => '/api/ingredients/'.$ingredient->getSlug(),
            'quantity' => '60',
            'unit' => 'ml',
            'position' => 1,
            'note' => 'Use a London dry style.',
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $client->request('GET', '/api/recipes/'.$slug, server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();

        $recipe = $this->jsonResponse($client);
        self::assertTrue($recipe['containsAlcoholComputed']);
        self::assertCount(1, $recipe['steps']);
        self::assertCount(1, $recipe['recipeIngredients']);
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
            sprintf('recipe-user-%s@example.com', $suffix),
            sprintf('recipe_user_%s', $suffix),
            new \DateTimeImmutable('1990-01-01'),
        );
        $user->setPassword($passwordHasher->hashPassword($user, $password));

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function createCategory(): Category
    {
        $category = new Category();
        $category->setName('Recipe Category '.bin2hex(random_bytes(4)));

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($category);
        $entityManager->flush();

        return $category;
    }

    private function createIngredient(bool $containsAlcohol): Ingredient
    {
        $ingredient = new Ingredient();
        $ingredient->setName('Recipe Ingredient '.bin2hex(random_bytes(4)));
        $ingredient->setContainsAlcohol($containsAlcohol);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($ingredient);
        $entityManager->flush();

        return $ingredient;
    }

    private function clearRecipes(): void
    {
        $connection = static::getContainer()->get(EntityManagerInterface::class)->getConnection();

        foreach (['favorite', 'recipe_ingredient', 'recipe_step', 'recipe_category', 'recipe'] as $table) {
            $connection->executeStatement(sprintf('DELETE FROM %s', $table));
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectionItems(KernelBrowser $client): array
    {
        $payload = $this->jsonResponse($client);

        if (isset($payload['member']) && is_array($payload['member'])) {
            return $payload['member'];
        }

        if (isset($payload['hydra:member']) && is_array($payload['hydra:member'])) {
            return $payload['hydra:member'];
        }

        return array_is_list($payload) ? $payload : [];
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
