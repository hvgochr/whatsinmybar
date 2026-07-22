<?php

namespace App\Tests\Comment;

use App\Entity\Recipe;
use App\Entity\User;
use App\Enum\RecipeStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class CommentApiTest extends WebTestCase
{
    public function testUserCanCreateThreadedCommentsOnPublishedRecipe(): void
    {
        $client = static::createClient();
        $this->clearCommentsAndRecipes();
        $token = $this->loginAsUser($client);
        $recipe = $this->createRecipe(RecipeStatus::Published);

        $client->jsonRequest('POST', '/api/recipes/'.$recipe->getSlug().'/comments', [
            'message' => 'Great recipe.',
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $parent = $this->jsonResponse($client);
        self::assertSame('Great recipe.', $parent['message']);
        self::assertNull($parent['parentId']);

        $client->jsonRequest('POST', '/api/recipes/'.$recipe->getSlug().'/comments', [
            'message' => 'Agreed.',
            'parentId' => $parent['id'],
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $reply = $this->jsonResponse($client);
        self::assertSame('Agreed.', $reply['message']);
        self::assertSame($parent['id'], $reply['parentId']);

        $client->request('GET', '/api/recipes/'.$recipe->getSlug().'/comments');

        self::assertResponseIsSuccessful();

        $items = $this->jsonResponse($client)['items'];
        self::assertCount(2, $items);
        self::assertSame('Great recipe.', $items[0]['message']);
        self::assertSame(1, $items[0]['replyCount']);
    }

    public function testAuthorCanUpdateAndSoftDeleteOwnComment(): void
    {
        $client = static::createClient();
        $this->clearCommentsAndRecipes();
        $token = $this->loginAsUser($client);
        $recipe = $this->createRecipe(RecipeStatus::Published);
        $commentId = $this->createComment($client, $token, $recipe, 'Original message');

        $client->jsonRequest('PATCH', '/api/comments/'.$commentId, [
            'message' => 'Updated message',
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();

        $updated = $this->jsonResponse($client);
        self::assertSame('Updated message', $updated['message']);

        $client->request('DELETE', '/api/comments/'.$commentId, server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();

        $deleted = $this->jsonResponse($client);
        self::assertTrue($deleted['deleted']);
        self::assertNull($deleted['message']);
        self::assertSame('removed', $deleted['moderationStatus']);
    }

    public function testNonAuthorCannotManageComment(): void
    {
        $client = static::createClient();
        $this->clearCommentsAndRecipes();
        $authorToken = $this->loginAsUser($client);
        $otherToken = $this->loginAsUser($client);
        $recipe = $this->createRecipe(RecipeStatus::Published);
        $commentId = $this->createComment($client, $authorToken, $recipe, 'Original message');

        $client->jsonRequest('PATCH', '/api/comments/'.$commentId, [
            'message' => 'Hijacked message',
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$otherToken,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAdminCanHideCommentAndMessageIsNotPubliclyExposed(): void
    {
        $client = static::createClient();
        $this->clearCommentsAndRecipes();
        $authorToken = $this->loginAsUser($client);
        $adminToken = $this->loginAsUser($client, roles: ['ROLE_ADMIN']);
        $recipe = $this->createRecipe(RecipeStatus::Published);
        $commentId = $this->createComment($client, $authorToken, $recipe, 'Moderate me');

        $client->jsonRequest('PATCH', '/api/comments/'.$commentId, [
            'moderationStatus' => 'hidden',
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$adminToken,
        ]);

        self::assertResponseIsSuccessful();

        $hidden = $this->jsonResponse($client);
        self::assertSame('hidden', $hidden['moderationStatus']);
        self::assertNull($hidden['message']);
    }

    public function testDraftRecipeCannotBeCommented(): void
    {
        $client = static::createClient();
        $this->clearCommentsAndRecipes();
        $token = $this->loginAsUser($client);
        $recipe = $this->createRecipe(RecipeStatus::Draft);

        $client->jsonRequest('POST', '/api/recipes/'.$recipe->getSlug().'/comments', [
            'message' => 'Should fail.',
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    private function createComment(KernelBrowser $client, string $token, Recipe $recipe, string $message): int
    {
        $client->jsonRequest('POST', '/api/recipes/'.$recipe->getSlug().'/comments', [
            'message' => $message,
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $payload = $this->jsonResponse($client);
        self::assertIsInt($payload['id']);

        return $payload['id'];
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
    private function createUser(string $password, array $roles = []): User
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $suffix = bin2hex(random_bytes(6));

        $user = new User(
            sprintf('comment-api-%s@example.com', $suffix),
            sprintf('comment_api_%s', $suffix),
            new \DateTimeImmutable('1990-01-01'),
        );
        $user->setRoles($roles);
        $user->setPassword($passwordHasher->hashPassword($user, $password));

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function createRecipe(RecipeStatus $status): Recipe
    {
        $author = $this->createUser('very-secure-password');
        $suffix = bin2hex(random_bytes(6));
        $recipe = new Recipe();
        $recipe->setAuthor($author);
        $recipe->setTitle(sprintf('Comment API Recipe %s', $suffix));
        $recipe->setDescription('Recipe used to test comment API endpoints.');
        $recipe->setStatus($status);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($recipe);
        $entityManager->flush();

        return $recipe;
    }

    private function clearCommentsAndRecipes(): void
    {
        $connection = static::getContainer()->get(EntityManagerInterface::class)->getConnection();

        foreach (['comment', 'favorite', 'recipe_ingredient', 'recipe_step', 'recipe_category', 'recipe'] as $table) {
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
