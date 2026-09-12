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

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        static::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertTrue(static::getContainer()->get(EntityManagerInterface::class)->getRepository(\App\Entity\Recipe::class)->findOneBy(['slug' => $slug])->getContainsAlcoholOverride());
    }

    public function testPublishedRecipeIsVisibleInPublicCollection(): void
    {
        $client = static::createClient();
        $this->clearRecipes();
        $token = $this->loginAsUser($client);
        $suffix = bin2hex(random_bytes(4));
        $slug = sprintf('public-negroni-%s', $suffix);

        $client->jsonRequest('POST', '/api/recipes/aggregate', [
            'title' => sprintf('Public Negroni %s', $suffix),
            'description' => 'A published cocktail.',
            'difficulty' => 'easy',
            'preparationTimeMinutes' => 3,
            'servings' => 1,
            'categories' => [],
            'steps' => [['instruction' => 'Mix.']],
            'ingredients' => [$this->aggregateIngredient($this->createIngredient(false), '30')],
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $recipe = $this->jsonResponse($client);
        self::assertSame($slug, $recipe['slug']);
        $client->request('POST', '/api/recipes/'.$slug.'/publish', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
        self::assertResponseIsSuccessful();
        self::assertSame('published', $this->jsonResponse($client)['status']);
        self::assertNotNull($this->jsonResponse($client)['publishedAt']);

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

    public function testAuthorCanCreateAndReplaceACompleteRecipeAggregate(): void
    {
        $client = static::createClient();
        $token = $this->loginAsUser($client);
        $category = $this->createCategory();
        $gin = $this->createIngredient(containsAlcohol: true);
        $vermouth = $this->createIngredient(containsAlcohol: false);
        $suffix = bin2hex(random_bytes(4));

        $client->jsonRequest('POST', '/api/recipes/aggregate', [
            'title' => sprintf('Transactional Martini %s', $suffix),
            'description' => 'The original aggregate.',
            'difficulty' => 'medium',
            'preparationTimeMinutes' => 5,
            'servings' => 1,
            'categories' => ['/api/categories/'.$category->getSlug()],
            'steps' => [
                ['instruction' => 'Stir with ice.'],
                ['instruction' => 'Strain into a glass.'],
            ],
            'ingredients' => [
                $this->aggregateIngredient($gin, '60'),
                $this->aggregateIngredient($vermouth, '15'),
            ],
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $created = $this->jsonResponse($client);
        self::assertSame('The original aggregate.', $created['description']);
        self::assertSame(['Stir with ice.', 'Strain into a glass.'], array_column($created['steps'], 'instruction'));
        self::assertSame([1, 2], array_column($created['steps'], 'position'));
        self::assertCount(2, $created['recipeIngredients']);
        self::assertTrue($created['containsAlcoholComputed']);

        $client->jsonRequest('PUT', '/api/recipes/'.$created['slug'].'/aggregate', [
            'title' => $created['title'],
            'description' => 'The replacement aggregate.',
            'difficulty' => 'hard',
            'preparationTimeMinutes' => 8,
            'servings' => 2,
            'categories' => [],
            'steps' => [
                ['instruction' => 'Garnish with a twist.'],
                ['instruction' => 'Stir gently.'],
            ],
            'ingredients' => [
                $this->aggregateIngredient($vermouth, '30', unit: 'cl'),
            ],
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();
        $updated = $this->jsonResponse($client);
        self::assertSame('The replacement aggregate.', $updated['description']);
        self::assertSame('hard', $updated['difficulty']);
        self::assertSame(['Garnish with a twist.', 'Stir gently.'], array_column($updated['steps'], 'instruction'));
        self::assertSame([1, 2], array_column($updated['steps'], 'position'));
        self::assertCount(1, $updated['recipeIngredients']);
        self::assertSame('30.00', $updated['recipeIngredients'][0]['quantity']);
        self::assertSame('cl', $updated['recipeIngredients'][0]['unit']);
        self::assertFalse($updated['containsAlcoholComputed']);
    }

    public function testInvalidAggregateUpdateLeavesTheStoredRecipeUnchanged(): void
    {
        $client = static::createClient();
        $token = $this->loginAsUser($client);
        $ingredient = $this->createIngredient(containsAlcohol: false);
        $suffix = bin2hex(random_bytes(4));

        $client->jsonRequest('POST', '/api/recipes/aggregate', [
            'title' => sprintf('Rollback Highball %s', $suffix),
            'description' => 'Keep this description.',
            'difficulty' => 'easy',
            'preparationTimeMinutes' => 3,
            'servings' => 1,
            'categories' => [],
            'steps' => [['instruction' => 'Keep this step.']],
            'ingredients' => [$this->aggregateIngredient($ingredient, '50')],
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $created = $this->jsonResponse($client);

        $client->jsonRequest('PUT', '/api/recipes/'.$created['slug'].'/aggregate', [
            'title' => $created['title'],
            'description' => 'This must not be stored.',
            'difficulty' => 'hard',
            'preparationTimeMinutes' => 10,
            'servings' => 4,
            'categories' => [],
            'steps' => [['instruction' => '']],
            'ingredients' => [$this->aggregateIngredient($ingredient, '25')],
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $client->request('GET', '/api/recipes/'.$created['slug'], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();
        $stored = $this->jsonResponse($client);
        self::assertSame('Keep this description.', $stored['description']);
        self::assertSame('easy', $stored['difficulty']);
        self::assertSame(['Keep this step.'], array_column($stored['steps'], 'instruction'));
        self::assertSame('50.00', $stored['recipeIngredients'][0]['quantity']);
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

    /**
     * @return array{ingredient: string, quantity: string, unit: string, note: null}
     */
    private function aggregateIngredient(Ingredient $ingredient, string $quantity, string $unit = 'ml'): array
    {
        return [
            'ingredient' => '/api/ingredients/'.$ingredient->getSlug(),
            'quantity' => $quantity,
            'unit' => $unit,
            'note' => null,
        ];
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
