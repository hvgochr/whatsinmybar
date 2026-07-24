<?php

namespace App\Tests\Recipe;

use App\Entity\Category;
use App\Entity\Ingredient;
use App\Entity\Recipe;
use App\Entity\RecipeIngredient;
use App\Entity\User;
use App\Enum\RecipeStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class RecipeSearchApiTest extends WebTestCase
{
    public function testTextSearchMatchesTitleDescriptionAndSlug(): void
    {
        $client = static::createClient();
        $this->clearRecipesAndTaxonomy();
        $titleMatch = $this->createRecipe(title: 'Ginger Fizz');
        $descriptionMatch = $this->createRecipe(title: 'Bright Cooler', description: 'A cocktail with ginger syrup.');
        $slugMatch = $this->createRecipe(title: 'Slug Match');
        $slugMatch->setSlug('ginger-slug-match');
        $miss = $this->createRecipe(title: 'Mint Tonic');
        $this->flush();

        $client->request('GET', '/api/recipes?'.http_build_query([
            'pagination' => 'false',
            'q' => 'ginger',
        ]));

        self::assertResponseIsSuccessful();

        $slugs = $this->collectionSlugs($client);
        self::assertContains($titleMatch->getSlug(), $slugs);
        self::assertContains($descriptionMatch->getSlug(), $slugs);
        self::assertContains($slugMatch->getSlug(), $slugs);
        self::assertNotContains($miss->getSlug(), $slugs);
    }

    public function testCollectionCanBeFilteredByCategoryIngredientAuthorAndAlcohol(): void
    {
        $client = static::createClient();
        $this->clearRecipesAndTaxonomy();
        $author = $this->createUser();
        $matchingCategory = $this->createCategory('Sours');
        $otherCategory = $this->createCategory('Highballs');
        $matchingIngredient = $this->createIngredient('Lime Juice');
        $otherIngredient = $this->createIngredient('Orange Juice');
        $match = $this->createRecipe(title: 'Filtered Sour', author: $author, containsAlcohol: false);
        $match->addCategory($matchingCategory);
        $this->addIngredient($match, $matchingIngredient);

        $wrongCategory = $this->createRecipe(title: 'Wrong Category', author: $author, containsAlcohol: false);
        $wrongCategory->addCategory($otherCategory);
        $this->addIngredient($wrongCategory, $matchingIngredient);

        $wrongIngredient = $this->createRecipe(title: 'Wrong Ingredient', author: $author, containsAlcohol: false);
        $wrongIngredient->addCategory($matchingCategory);
        $this->addIngredient($wrongIngredient, $otherIngredient);

        $wrongAlcohol = $this->createRecipe(title: 'Wrong Alcohol', author: $author, containsAlcohol: true);
        $wrongAlcohol->addCategory($matchingCategory);
        $this->addIngredient($wrongAlcohol, $matchingIngredient);
        $wrongAlcohol->setContainsAlcoholOverride(true);
        $this->flush();

        $client->request('GET', '/api/recipes?'.http_build_query([
            'pagination' => 'false',
            'category' => $matchingCategory->getSlug(),
            'ingredient' => $matchingIngredient->getSlug(),
            'author' => $author->getUsername(),
            'alcohol' => 'false',
        ]));

        self::assertResponseIsSuccessful();

        self::assertSame([$match->getSlug()], $this->collectionSlugs($client));
    }

    public function testCollectionCanBeFilteredByPopularityAndPublicationDate(): void
    {
        $client = static::createClient();
        $this->clearRecipesAndTaxonomy();
        $oldPopular = $this->createRecipe(title: 'Old Popular');
        $oldPopular->incrementFavoriteCount();
        $oldPopular->incrementFavoriteCount();
        $this->setPublishedAt($oldPopular, new \DateTimeImmutable('2026-01-10 12:00:00'));

        $newPopular = $this->createRecipe(title: 'New Popular');
        $newPopular->incrementFavoriteCount();
        $newPopular->incrementFavoriteCount();
        $newPopular->incrementFavoriteCount();
        $this->setPublishedAt($newPopular, new \DateTimeImmutable('2026-01-20 12:00:00'));

        $unpopular = $this->createRecipe(title: 'Unpopular');
        $this->setPublishedAt($unpopular, new \DateTimeImmutable('2026-01-30 12:00:00'));
        $this->flush();

        $client->request('GET', '/api/recipes?'.http_build_query([
            'pagination' => 'false',
            'minFavorites' => '2',
            'publishedAfter' => '2026-01-01',
            'publishedBefore' => '2026-01-25',
            'sort' => 'popular',
        ]));

        self::assertResponseIsSuccessful();

        self::assertSame([$newPopular->getSlug(), $oldPopular->getSlug()], $this->collectionSlugs($client));
    }

    public function testCollectionCanBeSortedByPublicationDate(): void
    {
        $client = static::createClient();
        $this->clearRecipesAndTaxonomy();
        $old = $this->createRecipe(title: 'Old Recipe');
        $this->setPublishedAt($old, new \DateTimeImmutable('2026-01-10 12:00:00'));
        $new = $this->createRecipe(title: 'New Recipe');
        $this->setPublishedAt($new, new \DateTimeImmutable('2026-01-20 12:00:00'));
        $this->flush();

        $client->request('GET', '/api/recipes?'.http_build_query([
            'pagination' => 'false',
            'sort' => 'oldest',
        ]));

        self::assertResponseIsSuccessful();
        self::assertSame([$old->getSlug(), $new->getSlug()], $this->collectionSlugs($client));

        $client->request('GET', '/api/recipes?'.http_build_query([
            'pagination' => 'false',
            'sort' => 'newest',
        ]));

        self::assertResponseIsSuccessful();
        self::assertSame([$new->getSlug(), $old->getSlug()], $this->collectionSlugs($client));
    }

    public function testInvalidSearchFilterReturnsBadRequest(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/recipes?'.http_build_query([
            'pagination' => 'false',
            'sort' => 'random',
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    private function createUser(): User
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $suffix = bin2hex(random_bytes(6));

        $user = new User(
            sprintf('recipe-search-%s@example.com', $suffix),
            sprintf('recipe_search_%s', $suffix),
            new \DateTimeImmutable('1990-01-01'),
        );
        $user->setPassword($passwordHasher->hashPassword($user, 'very-secure-password'));

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function createCategory(string $name): Category
    {
        $category = new Category();
        $category->setName($name.' '.bin2hex(random_bytes(4)));

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($category);
        $entityManager->flush();

        return $category;
    }

    private function createIngredient(string $name): Ingredient
    {
        $ingredient = new Ingredient();
        $ingredient->setName($name.' '.bin2hex(random_bytes(4)));

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($ingredient);
        $entityManager->flush();

        return $ingredient;
    }

    private function createRecipe(string $title, string $description = 'A recipe used for search tests.', ?User $author = null, bool $containsAlcohol = false): Recipe
    {
        $author ??= $this->createUser();
        $recipe = new Recipe();
        $recipe->setAuthor($author);
        $recipe->setTitle($title.' '.bin2hex(random_bytes(4)));
        $recipe->setDescription($description);
        $recipe->setStatus(RecipeStatus::Published);
        $recipe->setContainsAlcoholComputed($containsAlcohol);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($recipe);
        $entityManager->flush();

        return $recipe;
    }

    private function addIngredient(Recipe $recipe, Ingredient $ingredient): void
    {
        $recipeIngredient = new RecipeIngredient();
        $recipeIngredient->setRecipe($recipe);
        $recipeIngredient->setIngredient($ingredient);
        $recipeIngredient->setPosition($recipe->getRecipeIngredients()->count() + 1);
        $recipe->addRecipeIngredient($recipeIngredient);

        static::getContainer()->get(EntityManagerInterface::class)->persist($recipeIngredient);
    }

    private function setPublishedAt(Recipe $recipe, \DateTimeImmutable $publishedAt): void
    {
        $property = new \ReflectionProperty(Recipe::class, 'publishedAt');
        $property->setValue($recipe, $publishedAt);
    }

    private function flush(): void
    {
        static::getContainer()->get(EntityManagerInterface::class)->flush();
    }

    private function clearRecipesAndTaxonomy(): void
    {
        $connection = static::getContainer()->get(EntityManagerInterface::class)->getConnection();

        foreach (['report', 'comment', 'favorite', 'recipe_ingredient', 'recipe_step', 'recipe_category', 'recipe', 'category', 'ingredient'] as $table) {
            $connection->executeStatement(sprintf('DELETE FROM %s', $table));
        }
    }

    /**
     * @return list<string>
     */
    private function collectionSlugs(KernelBrowser $client): array
    {
        return array_map(
            static fn (array $recipe): string => (string) $recipe['slug'],
            $this->collectionItems($client),
        );
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
