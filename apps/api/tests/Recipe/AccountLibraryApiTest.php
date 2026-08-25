<?php

namespace App\Tests\Recipe;

use App\Entity\Favorite;
use App\Entity\Recipe;
use App\Entity\User;
use App\Enum\RecipeStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AccountLibraryApiTest extends WebTestCase
{
    public function testOwnedRecipesArePrivatePaginatedAndIncludeEveryWorkflowStatus(): void
    {
        $client = static::createClient();
        $owner = $this->createUser('owner', new \DateTimeImmutable('1990-01-01'));
        $otherUser = $this->createUser('other', new \DateTimeImmutable('1990-01-01'));
        $token = $this->login($client, $owner);

        $draft = $this->createRecipe($owner, 'Owned draft', RecipeStatus::Draft);
        $published = $this->createRecipe($owner, 'Owned published', RecipeStatus::Published);
        $archived = $this->createRecipe($owner, 'Owned archived', RecipeStatus::Archived);
        $this->createRecipe($otherUser, 'Other user draft', RecipeStatus::Draft);

        $client->request('GET', '/api/me/recipes?page=1&pageSize=2', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();
        $firstPage = $this->jsonResponse($client);
        self::assertSame(1, $firstPage['page']);
        self::assertSame(2, $firstPage['pageSize']);
        self::assertSame(3, $firstPage['totalItems']);
        self::assertSame(2, $firstPage['totalPages']);
        self::assertCount(2, $firstPage['items']);

        $client->request('GET', '/api/me/recipes?page=2&pageSize=2', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();
        $allItems = [...$firstPage['items'], ...$this->jsonResponse($client)['items']];
        self::assertEqualsCanonicalizing(
            [$draft->getSlug(), $published->getSlug(), $archived->getSlug()],
            array_column($allItems, 'slug'),
        );
        self::assertEqualsCanonicalizing(['draft', 'published', 'archived'], array_column($allItems, 'status'));
    }

    public function testSavedRecipesArePrivatePaginatedAndViewerCorrect(): void
    {
        $client = static::createClient();
        $user = $this->createUser('saved', new \DateTimeImmutable('1990-01-01'));
        $otherUser = $this->createUser('other-saved', new \DateTimeImmutable('1990-01-01'));
        $token = $this->login($client, $user);
        $savedRecipes = [
            $this->createRecipe($otherUser, 'Saved one', RecipeStatus::Published),
            $this->createRecipe($otherUser, 'Saved two', RecipeStatus::Published),
            $this->createRecipe($otherUser, 'Saved three', RecipeStatus::Published),
        ];
        foreach ($savedRecipes as $recipe) {
            $this->favorite($user, $recipe);
        }

        $notSaved = $this->createRecipe($otherUser, 'Not saved', RecipeStatus::Published);
        $this->favorite($otherUser, $notSaved);

        $client->request('GET', '/api/me/saved-recipes?page=1&pageSize=2', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();
        $firstPage = $this->jsonResponse($client);
        self::assertSame(3, $firstPage['totalItems']);
        self::assertSame(2, $firstPage['totalPages']);
        self::assertCount(2, $firstPage['items']);
        foreach ($firstPage['items'] as $item) {
            self::assertTrue($item['favorited']);
        }

        $client->request('GET', '/api/me/saved-recipes?page=2&pageSize=2', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();
        $allItems = [...$firstPage['items'], ...$this->jsonResponse($client)['items']];
        self::assertEqualsCanonicalizing(
            array_map(static fn (Recipe $recipe): string => $recipe->getSlug(), $savedRecipes),
            array_column($allItems, 'slug'),
        );
        self::assertNotContains($notSaved->getSlug(), array_column($allItems, 'slug'));
    }

    public function testMinorLibraryDoesNotExposeAlcoholicRecipes(): void
    {
        $client = static::createClient();
        $minor = $this->createUser('minor', new \DateTimeImmutable('2012-01-01'));
        $author = $this->createUser('adult-author', new \DateTimeImmutable('1990-01-01'));
        $token = $this->login($client, $minor);
        $alcoholicSavedRecipe = $this->createRecipe($author, 'Hidden alcoholic favorite', RecipeStatus::Published, true);
        $visibleSavedRecipe = $this->createRecipe($author, 'Visible zero proof favorite', RecipeStatus::Published);
        $this->favorite($minor, $alcoholicSavedRecipe);
        $this->favorite($minor, $visibleSavedRecipe);
        $this->createRecipe($minor, 'Hidden alcoholic draft', RecipeStatus::Draft, true);

        foreach (['/api/me/saved-recipes', '/api/me/recipes'] as $path) {
            $client->request('GET', $path, server: [
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ]);

            self::assertResponseIsSuccessful();
            $payload = $this->jsonResponse($client);
            self::assertNotContains($alcoholicSavedRecipe->getSlug(), array_column($payload['items'], 'slug'));
            foreach ($payload['items'] as $item) {
                self::assertFalse($item['containsAlcohol']);
            }
        }
    }

    public function testLibraryEndpointsRequireAuthenticationAndValidatePagination(): void
    {
        $client = static::createClient();

        foreach (['/api/me/recipes', '/api/me/saved-recipes'] as $path) {
            $client->request('GET', $path);
            self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        }

        $user = $this->createUser('pagination', new \DateTimeImmutable('1990-01-01'));
        $token = $this->login($client, $user);
        $client->request('GET', '/api/me/recipes?pageSize=101', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertSame('pageSize must be between 1 and 100.', $this->jsonResponse($client)['error']['message']);
    }

    private function createUser(string $prefix, \DateTimeImmutable $birthDate): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $user = new User(
            sprintf('%s-%s@example.com', $prefix, $suffix),
            sprintf('%s_%s', str_replace('-', '_', $prefix), $suffix),
            $birthDate,
        );
        $user->setPassword($passwordHasher->hashPassword($user, 'very-secure-password'));
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function createRecipe(User $author, string $title, RecipeStatus $status, bool $containsAlcohol = false): Recipe
    {
        $recipe = new Recipe();
        $recipe->setAuthor($author);
        $recipe->setTitle($title.' '.bin2hex(random_bytes(4)));
        $recipe->setDescription('Recipe used to test the private account library.');
        $recipe->setStatus($status);
        $recipe->setContainsAlcoholComputed($containsAlcohol);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($recipe);
        $entityManager->flush();

        return $recipe;
    }

    private function favorite(User $user, Recipe $recipe): void
    {
        $recipe->incrementFavoriteCount();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(new Favorite($user, $recipe));
        $entityManager->flush();
    }

    private function login(KernelBrowser $client, User $user): string
    {
        $client->jsonRequest('POST', '/api/auth/login', [
            'email' => $user->getEmail(),
            'password' => 'very-secure-password',
        ]);
        self::assertResponseIsSuccessful();

        return $this->jsonResponse($client)['token'];
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
