<?php

namespace App\Tests\Recipe;

use App\Entity\Recipe;
use App\Entity\User;
use App\Enum\RecipeStatus;
use App\Repository\RecipeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class RecipeWorkflowApiTest extends WebTestCase
{
    public function testAuthorCanPublishAndArchiveRecipe(): void
    {
        $client = static::createClient();
        $this->clearRecipes();
        $author = $this->createUser();
        $token = $this->loginAsUser($client, $author);
        $recipe = $this->createRecipe($author, RecipeStatus::Draft);

        $client->request('POST', '/api/recipes/'.$recipe->getSlug().'/publish', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();

        $published = $this->jsonResponse($client);
        self::assertSame($recipe->getSlug(), $published['slug']);
        self::assertSame('published', $published['status']);
        self::assertIsString($published['publishedAt']);
        self::assertFalse($published['deleted']);

        $client->request('GET', '/api/recipes/'.$recipe->getSlug());

        self::assertResponseIsSuccessful();

        $client->request('POST', '/api/recipes/'.$recipe->getSlug().'/archive', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();

        $archived = $this->jsonResponse($client);
        self::assertSame('archived', $archived['status']);
        self::assertSame($published['publishedAt'], $archived['publishedAt']);

        $client->request('GET', '/api/recipes/'.$recipe->getSlug());

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $client->request('GET', '/api/recipes/'.$recipe->getSlug(), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();
    }

    public function testNonAuthorCannotPublishRecipe(): void
    {
        $client = static::createClient();
        $this->clearRecipes();
        $author = $this->createUser();
        $other = $this->createUser();
        $token = $this->loginAsUser($client, $other);
        $recipe = $this->createRecipe($author, RecipeStatus::Draft);

        $client->request('POST', '/api/recipes/'.$recipe->getSlug().'/publish', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testDeleteSoftDeletesRecipeThroughApi(): void
    {
        $client = static::createClient();
        $this->clearRecipes();
        $author = $this->createUser();
        $token = $this->loginAsUser($client, $author);
        $recipe = $this->createRecipe($author, RecipeStatus::Published);
        $slug = $recipe->getSlug();

        $client->request('DELETE', '/api/recipes/'.$slug, server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        $storedRecipe = static::getContainer()->get(RecipeRepository::class)->findOneBy(['slug' => $slug]);
        self::assertInstanceOf(Recipe::class, $storedRecipe);
        self::assertNotNull($storedRecipe->getDeletedAt());
        self::assertSame('removed', $storedRecipe->getModerationStatus()->value);

        $client->request('GET', '/api/recipes/'.$slug, server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    private function loginAsUser(KernelBrowser $client, User $user): string
    {
        $client->jsonRequest('POST', '/api/auth/login', [
            'email' => $user->getEmail(),
            'password' => 'very-secure-password',
        ]);

        self::assertResponseIsSuccessful();

        $payload = $this->jsonResponse($client);
        self::assertIsString($payload['token']);

        return $payload['token'];
    }

    private function createUser(): User
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $suffix = bin2hex(random_bytes(6));

        $user = new User(
            sprintf('recipe-workflow-%s@example.com', $suffix),
            sprintf('recipe_workflow_%s', $suffix),
            new \DateTimeImmutable('1990-01-01'),
        );
        $user->setPassword($passwordHasher->hashPassword($user, 'very-secure-password'));

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function createRecipe(User $author, RecipeStatus $status): Recipe
    {
        $recipe = new Recipe();
        $recipe->setAuthor($author);
        $recipe->setTitle('Recipe Workflow '.bin2hex(random_bytes(4)));
        $recipe->setDescription('Recipe used for workflow endpoint tests.');
        $recipe->setStatus($status);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($recipe);
        $entityManager->flush();

        return $recipe;
    }

    private function clearRecipes(): void
    {
        $connection = static::getContainer()->get(EntityManagerInterface::class)->getConnection();

        foreach (['report', 'comment', 'favorite', 'recipe_ingredient', 'recipe_step', 'recipe_category', 'recipe'] as $table) {
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
