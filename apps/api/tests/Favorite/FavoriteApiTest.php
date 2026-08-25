<?php

namespace App\Tests\Favorite;

use App\Entity\Recipe;
use App\Entity\User;
use App\Enum\RecipeStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class FavoriteApiTest extends WebTestCase
{
    public function testUserCanAddAndRemoveFavoriteIdempotently(): void
    {
        $client = static::createClient();
        $token = $this->loginAsUser($client, new \DateTimeImmutable('1990-01-01'));
        $recipe = $this->createRecipe(RecipeStatus::Published, containsAlcohol: false);

        $client->request('POST', '/api/recipes/'.$recipe->getSlug().'/favorite', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();

        $firstAdd = $this->jsonResponse($client);
        self::assertSame($recipe->getSlug(), $firstAdd['recipeSlug']);
        self::assertSame(1, $firstAdd['favoriteCount']);
        self::assertTrue($firstAdd['favorited']);
        self::assertTrue($firstAdd['changed']);

        $client->request('POST', '/api/recipes/'.$recipe->getSlug().'/favorite', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();

        $secondAdd = $this->jsonResponse($client);
        self::assertSame(1, $secondAdd['favoriteCount']);
        self::assertTrue($secondAdd['favorited']);
        self::assertFalse($secondAdd['changed']);

        $client->request('GET', '/api/recipes/'.$recipe->getSlug(), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();

        $recipePayload = $this->jsonResponse($client);
        self::assertSame(1, $recipePayload['favoriteCount']);
        self::assertTrue($recipePayload['favorited']);

        $client->request('GET', '/api/recipes', server: [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();
        $collectionPayload = $this->jsonResponse($client);
        $collectionRecipe = array_values(array_filter(
            $collectionPayload,
            static fn (array $item): bool => $recipe->getSlug() === $item['slug'],
        ))[0];
        self::assertTrue($collectionRecipe['favorited']);

        $client->request('GET', '/api/recipes/'.$recipe->getSlug());

        self::assertResponseIsSuccessful();
        self::assertFalse($this->jsonResponse($client)['favorited']);

        $client->request('DELETE', '/api/recipes/'.$recipe->getSlug().'/favorite', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();

        $firstRemove = $this->jsonResponse($client);
        self::assertSame(0, $firstRemove['favoriteCount']);
        self::assertFalse($firstRemove['favorited']);
        self::assertTrue($firstRemove['changed']);

        $client->request('DELETE', '/api/recipes/'.$recipe->getSlug().'/favorite', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();

        $secondRemove = $this->jsonResponse($client);
        self::assertSame(0, $secondRemove['favoriteCount']);
        self::assertFalse($secondRemove['favorited']);
        self::assertFalse($secondRemove['changed']);

        $client->request('GET', '/api/recipes/'.$recipe->getSlug(), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();
        self::assertFalse($this->jsonResponse($client)['favorited']);
    }

    public function testMinorCannotFavoriteAlcoholicRecipe(): void
    {
        $client = static::createClient();
        $token = $this->loginAsUser($client, new \DateTimeImmutable('2012-01-01'));
        $recipe = $this->createRecipe(RecipeStatus::Published, containsAlcohol: true);

        $client->request('POST', '/api/recipes/'.$recipe->getSlug().'/favorite', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testDraftRecipeCannotBeFavorited(): void
    {
        $client = static::createClient();
        $user = $this->createUser('very-secure-password', new \DateTimeImmutable('1990-01-01'));
        $recipe = $this->createRecipe(RecipeStatus::Draft, containsAlcohol: false, author: $user);

        $client->jsonRequest('POST', '/api/auth/login', [
            'email' => $user->getEmail(),
            'password' => 'very-secure-password',
        ]);

        self::assertResponseIsSuccessful();

        $loginPayload = $this->jsonResponse($client);

        $client->request('POST', '/api/recipes/'.$recipe->getSlug().'/favorite', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$loginPayload['token'],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    private function loginAsUser(KernelBrowser $client, \DateTimeImmutable $birthDate): string
    {
        $password = 'very-secure-password';
        $user = $this->createUser($password, $birthDate);

        $client->jsonRequest('POST', '/api/auth/login', [
            'email' => $user->getEmail(),
            'password' => $password,
        ]);

        self::assertResponseIsSuccessful();

        $payload = $this->jsonResponse($client);
        self::assertIsString($payload['token']);

        return $payload['token'];
    }

    private function createUser(string $password, \DateTimeImmutable $birthDate): User
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $suffix = bin2hex(random_bytes(6));

        $user = new User(
            sprintf('favorite-api-%s@example.com', $suffix),
            sprintf('favorite_api_%s', $suffix),
            $birthDate,
        );
        $user->setPassword($passwordHasher->hashPassword($user, $password));

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function createRecipe(RecipeStatus $status, bool $containsAlcohol, ?User $author = null): Recipe
    {
        $suffix = bin2hex(random_bytes(6));
        $author ??= $this->createUser('very-secure-password', new \DateTimeImmutable('1990-01-01'));
        $recipe = new Recipe();
        $recipe->setAuthor($author);
        $recipe->setTitle(sprintf('Favorite API Recipe %s', $suffix));
        $recipe->setDescription('Recipe used to test favorite API endpoints.');
        $recipe->setStatus($status);
        $recipe->setContainsAlcoholComputed($containsAlcohol);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($recipe);
        $entityManager->flush();

        return $recipe;
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
