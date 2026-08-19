<?php

namespace App\Tests\Comment;

use App\Entity\Comment;
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
    public function testCommentMessageLengthIsEnforcedOnCreateAndUpdate(): void
    {
        $client = static::createClient();
        $this->clearCommentsAndRecipes();
        $token = $this->loginAsUser($client);
        $recipe = $this->createRecipe(RecipeStatus::Published);
        $maximumLengthMessage = str_repeat('a', 2000);

        $commentId = $this->createComment($client, $token, $recipe, $maximumLengthMessage);

        $client->jsonRequest('POST', '/api/recipes/'.$recipe->getSlug().'/comments', [
            'message' => str_repeat('a', 2001),
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationError($client);

        $client->jsonRequest('PATCH', '/api/comments/'.$commentId, [
            'message' => str_repeat('a', 2001),
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationError($client);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $storedComment = $entityManager->getRepository(Comment::class)->find($commentId);

        self::assertInstanceOf(Comment::class, $storedComment);
        self::assertSame($maximumLengthMessage, $storedComment->getMessage());
        self::assertSame(1, $entityManager->getRepository(Comment::class)->count([]));
    }

    public function testCommentCreatePayloadIsStrictlyValidated(): void
    {
        $client = static::createClient();
        $this->clearCommentsAndRecipes();
        $token = $this->loginAsUser($client);
        $recipe = $this->createRecipe(RecipeStatus::Published);

        foreach ([
            [],
            ['message' => '   '],
            ['message' => null],
            ['message' => 123],
            ['message' => 'Valid message', 'unexpected' => true],
        ] as $payload) {
            $client->jsonRequest('POST', '/api/recipes/'.$recipe->getSlug().'/comments', $payload, server: [
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ]);

            self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
            $this->assertValidationError($client);
        }

        self::assertSame(0, static::getContainer()->get(EntityManagerInterface::class)->getRepository(Comment::class)->count([]));
    }

    public function testCommentUpdateAllowsMissingMessageButRejectsInvalidValuesAndUnexpectedFields(): void
    {
        $client = static::createClient();
        $this->clearCommentsAndRecipes();
        $token = $this->loginAsUser($client);
        $recipe = $this->createRecipe(RecipeStatus::Published);
        $commentId = $this->createComment($client, $token, $recipe, 'Original message');

        $client->jsonRequest('PATCH', '/api/comments/'.$commentId, [], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame('Original message', $this->jsonResponse($client)['message']);

        foreach ([
            ['message' => ''],
            ['message' => null],
            ['message' => ['not a string']],
            ['unexpected' => true],
        ] as $payload) {
            $client->jsonRequest('PATCH', '/api/comments/'.$commentId, $payload, server: [
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ]);

            self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
            $this->assertValidationError($client);
        }
    }

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

    public function testNonAdminCannotUpdateModerationStatus(): void
    {
        $client = static::createClient();
        $this->clearCommentsAndRecipes();
        $token = $this->loginAsUser($client);
        $recipe = $this->createRecipe(RecipeStatus::Published);
        $commentId = $this->createComment($client, $token, $recipe, 'Visible comment');

        $client->jsonRequest('PATCH', '/api/comments/'.$commentId, [
            'moderationStatus' => 'hidden',
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testParentMustBelongToSameRecipe(): void
    {
        $client = static::createClient();
        $this->clearCommentsAndRecipes();
        $token = $this->loginAsUser($client);
        $firstRecipe = $this->createRecipe(RecipeStatus::Published);
        $secondRecipe = $this->createRecipe(RecipeStatus::Published);
        $parentId = $this->createComment($client, $token, $firstRecipe, 'Parent comment');

        $client->jsonRequest('POST', '/api/recipes/'.$secondRecipe->getSlug().'/comments', [
            'message' => 'Invalid reply.',
            'parentId' => $parentId,
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testMinorCannotReadOrCreateCommentsOnAlcoholicRecipe(): void
    {
        $client = static::createClient();
        $this->clearCommentsAndRecipes();
        $minorToken = $this->loginAsUser($client, birthDate: new \DateTimeImmutable('2012-01-01'));
        $recipe = $this->createRecipe(RecipeStatus::Published, containsAlcohol: true);

        $client->request('GET', '/api/recipes/'.$recipe->getSlug().'/comments', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$minorToken,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $client->jsonRequest('POST', '/api/recipes/'.$recipe->getSlug().'/comments', [
            'message' => 'I should not see this recipe.',
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$minorToken,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
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
    private function loginAsUser(KernelBrowser $client, array $roles = [], ?\DateTimeImmutable $birthDate = null): string
    {
        $password = 'very-secure-password';
        $user = $this->createUser($password, $roles, $birthDate);

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
    private function createUser(string $password, array $roles = [], ?\DateTimeImmutable $birthDate = null): User
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $suffix = bin2hex(random_bytes(6));

        $user = new User(
            sprintf('comment-api-%s@example.com', $suffix),
            sprintf('comment_api_%s', $suffix),
            $birthDate ?? new \DateTimeImmutable('1990-01-01'),
        );
        $user->setRoles($roles);
        $user->setPassword($passwordHasher->hashPassword($user, $password));

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function createRecipe(RecipeStatus $status, bool $containsAlcohol = false): Recipe
    {
        $author = $this->createUser('very-secure-password');
        $suffix = bin2hex(random_bytes(6));
        $recipe = new Recipe();
        $recipe->setAuthor($author);
        $recipe->setTitle(sprintf('Comment API Recipe %s', $suffix));
        $recipe->setDescription('Recipe used to test comment API endpoints.');
        $recipe->setStatus($status);
        $recipe->setContainsAlcoholComputed($containsAlcohol);

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

    private function assertValidationError(KernelBrowser $client): void
    {
        $payload = $this->jsonResponse($client);

        self::assertSame(422, $payload['error']['status']);
        self::assertSame('validation_failed', $payload['error']['code']);
        self::assertSame('Validation failed.', $payload['error']['message']);
        self::assertNotEmpty($payload['error']['violations']);
    }
}
