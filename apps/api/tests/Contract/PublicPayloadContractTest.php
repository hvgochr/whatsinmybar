<?php

namespace App\Tests\Contract;

use App\Entity\Ingredient;
use App\Entity\Recipe;
use App\Entity\RecipeIngredient;
use App\Entity\RecipeStep;
use App\Entity\User;
use App\Enum\RecipeStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PublicPayloadContractTest extends WebTestCase
{
    public function testAccountPayloadKeysAreStable(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $token = $this->loginAsUser($client, $user);

        $client->request('GET', '/api/me', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame([
            'id',
            'email',
            'username',
            'birthDate',
            'bio',
            'avatarPath',
            'roles',
            'createdAt',
            'updatedAt',
        ], array_keys($this->jsonResponse($client)));

        $client->request('GET', '/api/users/'.$user->getUsername());

        self::assertResponseIsSuccessful();
        self::assertSame([
            'id',
            'username',
            'bio',
            'avatarPath',
            'createdAt',
        ], array_keys($this->jsonResponse($client)));
    }

    public function testRecipeWorkflowAndFavoritePayloadKeysAreStable(): void
    {
        $client = static::createClient();
        $this->clearRecipes();
        $user = $this->createUser();
        $token = $this->loginAsUser($client, $user);
        $recipe = $this->createRecipe($user, RecipeStatus::Draft);

        $client->request('POST', '/api/recipes/'.$recipe->getSlug().'/publish', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame([
            'id',
            'title',
            'slug',
            'status',
            'moderationStatus',
            'publishedAt',
            'deleted',
            'deletedAt',
            'updatedAt',
        ], array_keys($this->jsonResponse($client)));

        $client->request('POST', '/api/recipes/'.$recipe->getSlug().'/favorite', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame([
            'recipeSlug',
            'favoriteCount',
            'favorited',
            'changed',
        ], array_keys($this->jsonResponse($client)));
    }

    public function testCommentPayloadKeysAreStable(): void
    {
        $client = static::createClient();
        $this->clearRecipes();
        $user = $this->createUser();
        $token = $this->loginAsUser($client, $user);
        $recipe = $this->createRecipe($user, RecipeStatus::Published);

        $client->jsonRequest('POST', '/api/recipes/'.$recipe->getSlug().'/comments', [
            'message' => 'Stable payload.',
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame([
            'id',
            'recipeSlug',
            'authorUsername',
            'parentId',
            'message',
            'moderationStatus',
            'replyCount',
            'deleted',
            'createdAt',
            'updatedAt',
        ], array_keys($this->jsonResponse($client)));
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
            sprintf('contract-%s@example.com', $suffix),
            sprintf('contract_%s', $suffix),
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
        $recipe->setTitle('Contract Recipe '.bin2hex(random_bytes(4)));
        $recipe->setDescription('Recipe used for payload contract tests.');
        $recipe->setStatus($status);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $ingredient = new Ingredient();
        $ingredient->setName('Juice '.bin2hex(random_bytes(6)));
        $entityManager->persist($ingredient);
        $step = new RecipeStep();
        $step->setInstruction('Stir with ice.');
        $recipe->addStep($step);
        $part = new RecipeIngredient();
        $part->setIngredient($ingredient);
        $part->setQuantity('30');
        $recipe->addRecipeIngredient($part);
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
