<?php

namespace App\Tests\Report;

use App\Entity\Comment;
use App\Entity\Recipe;
use App\Entity\RefreshToken;
use App\Entity\Report;
use App\Entity\User;
use App\Enum\CommentModerationStatus;
use App\Enum\RecipeModerationStatus;
use App\Enum\RecipeStatus;
use App\Repository\CommentRepository;
use App\Repository\RecipeRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ReportApiTest extends WebTestCase
{
    public function testReportMessageLengthIsEnforced(): void
    {
        $client = static::createClient();
        $this->clearReportsAndContent();
        $token = $this->loginAsUser($client);
        $recipe = $this->createRecipe(RecipeStatus::Published);

        $client->jsonRequest('POST', '/api/reports', [
            'targetType' => 'recipe',
            'targetId' => $recipe->getId(),
            'reason' => 'spam',
            'message' => str_repeat('a', 2000),
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $client->jsonRequest('POST', '/api/reports', [
            'targetType' => 'recipe',
            'targetId' => $recipe->getId(),
            'reason' => 'spam',
            'message' => str_repeat('a', 2001),
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationError($client);
        self::assertSame(1, static::getContainer()->get(EntityManagerInterface::class)->getRepository(Report::class)->count([]));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidPayloadCases')]
    public function testReportPayloadIsStrictlyValidated(int $case): void
    {
        $client = static::createClient();
        $this->clearReportsAndContent();
        $token = $this->loginAsUser($client);
        $recipe = $this->createRecipe(RecipeStatus::Published);

        $payloads = [
            [],
            ['targetType' => '', 'targetId' => $recipe->getId(), 'reason' => 'spam'],
            ['targetType' => null, 'targetId' => $recipe->getId(), 'reason' => 'spam'],
            ['targetType' => 'recipe', 'targetId' => (string) $recipe->getId(), 'reason' => 'spam'],
            ['targetType' => 'recipe', 'targetId' => $recipe->getId(), 'reason' => ['spam']],
            ['targetType' => 'recipe', 'targetId' => $recipe->getId(), 'reason' => 'spam', 'message' => 123],
            ['targetType' => 'recipe', 'targetId' => $recipe->getId(), 'reason' => 'spam', 'unexpected' => true],
        ];
        $payload = $payloads[$case];
        $client->jsonRequest('POST', '/api/reports', $payload, server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationError($client);

        self::assertSame(0, static::getContainer()->get(EntityManagerInterface::class)->getRepository(Report::class)->count([]));
    }

    /** @return iterable<string, array{int}> */
    public static function invalidPayloadCases(): iterable
    {
        foreach (['empty', 'empty type', 'null type', 'string ID', 'array reason', 'numeric message', 'unknown field'] as $case => $name) {
            yield $name => [$case];
        }
    }

    public function testReportMessageMayBeEmptyMissingOrNull(): void
    {
        $client = static::createClient();
        $this->clearReportsAndContent();
        $token = $this->loginAsUser($client);
        $recipe = $this->createRecipe(RecipeStatus::Published);

        foreach (['missing', '', null] as $message) {
            $payload = [
                'targetType' => 'recipe',
                'targetId' => $recipe->getId(),
                'reason' => 'spam',
            ];
            if ('missing' !== $message) {
                $payload['message'] = $message;
            }

            $client->jsonRequest('POST', '/api/reports', $payload, server: [
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ]);

            self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
            self::assertNull($this->jsonResponse($client)['message']);
        }
    }

    public function testUserCanCreateReportForVisibleRecipe(): void
    {
        $client = static::createClient();
        $this->clearReportsAndContent();
        $token = $this->loginAsUser($client);
        $recipe = $this->createRecipe(RecipeStatus::Published);

        $client->jsonRequest('POST', '/api/reports', [
            'targetType' => 'recipe',
            'targetId' => $recipe->getId(),
            'reason' => 'spam',
            'message' => ' This looks like spam. ',
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $payload = $this->jsonResponse($client);
        self::assertSame('recipe', $payload['targetType']);
        self::assertSame($recipe->getId(), $payload['targetId']);
        self::assertSame('spam', $payload['reason']);
        self::assertSame('This looks like spam.', $payload['message']);
        self::assertSame('open', $payload['status']);
        self::assertNull($payload['reviewedByUsername']);
        self::assertNull($payload['reviewedAt']);
        self::assertArrayNotHasKey('targetContext', $payload);
    }

    public function testAdminCanListAndReviewReports(): void
    {
        $client = static::createClient();
        $this->clearReportsAndContent();
        $reporterToken = $this->loginAsUser($client);
        $adminToken = $this->loginAsUser($client, roles: ['ROLE_ADMIN']);
        $recipe = $this->createRecipe(RecipeStatus::Published);
        $reportId = $this->createRecipeReport($client, $reporterToken, $recipe);

        $client->request('GET', '/api/admin/reports', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$adminToken,
        ]);

        self::assertResponseIsSuccessful();

        $page = $this->jsonResponse($client);
        self::assertSame(1, $page['page']);
        self::assertSame(20, $page['pageSize']);
        self::assertSame(1, $page['totalItems']);
        self::assertSame(1, $page['totalPages']);
        $items = $page['items'];
        self::assertIsArray($items);
        self::assertNotEmpty($items);
        self::assertSame($reportId, $items[0]['id']);
        self::assertSame([
            'type' => 'recipe',
            'title' => $recipe->getTitle(),
            'slug' => $recipe->getSlug(),
            'authorUsername' => $recipe->getAuthorUsername(),
            'description' => $recipe->getDescription(),
            'status' => 'published',
            'moderationStatus' => 'visible',
            'deleted' => false,
        ], $items[0]['targetContext']);

        $client->jsonRequest('PATCH', '/api/admin/reports/'.$reportId, [
            'status' => 'reviewing',
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$adminToken,
        ]);

        self::assertResponseIsSuccessful();

        $payload = $this->jsonResponse($client);
        self::assertSame('reviewing', $payload['status']);
        self::assertIsString($payload['reviewedByUsername']);
        self::assertIsString($payload['reviewedAt']);
    }

    public function testAdminCanApplyRecipeModerationFromReport(): void
    {
        $client = static::createClient();
        $this->clearReportsAndContent();
        $reporterToken = $this->loginAsUser($client);
        $adminToken = $this->loginAsUser($client, roles: ['ROLE_ADMIN']);
        $readerToken = $this->loginAsUser($client);
        $recipe = $this->createRecipe(RecipeStatus::Published);
        $reportId = $this->createRecipeReport($client, $reporterToken, $recipe);

        $client->jsonRequest('PATCH', '/api/admin/reports/'.$reportId, [
            'status' => 'resolved',
            'moderationStatus' => 'hidden',
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$adminToken,
        ]);

        self::assertResponseIsSuccessful();

        $payload = $this->jsonResponse($client);
        self::assertSame('resolved', $payload['status']);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        $storedRecipe = static::getContainer()->get(RecipeRepository::class)->find($recipe->getId());
        self::assertInstanceOf(Recipe::class, $storedRecipe);
        self::assertSame(RecipeModerationStatus::Hidden, $storedRecipe->getModerationStatus());

        $client->request('GET', '/api/recipes/'.$recipe->getSlug(), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$readerToken,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAdminCanApplyCommentModerationFromReport(): void
    {
        $client = static::createClient();
        $this->clearReportsAndContent();
        $reporterToken = $this->loginAsUser($client);
        $adminToken = $this->loginAsUser($client, roles: ['ROLE_ADMIN']);
        $recipe = $this->createRecipe(RecipeStatus::Published);
        $comment = $this->createComment($recipe);
        $reportId = $this->createCommentReport($client, $reporterToken, $comment);

        $client->jsonRequest('PATCH', '/api/admin/reports/'.$reportId, [
            'status' => 'resolved',
            'moderationStatus' => 'hidden',
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$adminToken,
        ]);

        self::assertResponseIsSuccessful();

        $payload = $this->jsonResponse($client);
        self::assertSame('resolved', $payload['status']);
        self::assertSame('Comment to report.', $payload['targetContext']['message']);
        self::assertSame('hidden', $payload['targetContext']['moderationStatus']);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        $storedComment = static::getContainer()->get(CommentRepository::class)->find($comment->getId());
        self::assertInstanceOf(Comment::class, $storedComment);
        self::assertSame(CommentModerationStatus::Hidden, $storedComment->getModerationStatus());
    }

    public function testAdminCanApplyUserModerationFromReport(): void
    {
        $client = static::createClient();
        $this->clearReportsAndContent();
        $reporterToken = $this->loginAsUser($client);
        $adminToken = $this->loginAsUser($client, roles: ['ROLE_ADMIN']);
        $targetUser = $this->createUser('very-secure-password');
        $targetTokens = $this->loginExistingUser($client, $targetUser);
        $reportId = $this->createUserReport($client, $reporterToken, $targetUser);

        self::assertGreaterThan(0, $this->refreshTokenCount($targetUser));

        $client->jsonRequest('PATCH', '/api/admin/reports/'.$reportId, [
            'status' => 'resolved',
            'moderationStatus' => 'removed',
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$adminToken,
        ]);

        self::assertResponseIsSuccessful();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        $storedUser = static::getContainer()->get(UserRepository::class)->find($targetUser->getId());
        self::assertInstanceOf(User::class, $storedUser);
        self::assertNotNull($storedUser->getDeletedAt());
        self::assertSame(0, $this->refreshTokenCount($storedUser));

        $client->request('GET', '/api/me', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$targetTokens['token'],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $client->jsonRequest('PATCH', '/api/admin/reports/'.$reportId, [
            'moderationStatus' => 'visible',
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$adminToken,
        ]);

        self::assertResponseIsSuccessful();

        $entityManager->clear();

        $storedUser = static::getContainer()->get(UserRepository::class)->find($targetUser->getId());
        self::assertInstanceOf(User::class, $storedUser);
        self::assertNull($storedUser->getDeletedAt());

        $this->loginExistingUser($client, $storedUser);
        self::assertResponseIsSuccessful();
    }

    public function testNonAdminCannotListReports(): void
    {
        $client = static::createClient();
        $this->clearReportsAndContent();
        $token = $this->loginAsUser($client);

        $client->request('GET', '/api/admin/reports', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testReportModerationCannotDeleteLastActiveAdmin(): void
    {
        $client = static::createClient();
        $this->clearReportsAndContent();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        foreach ($entityManager->getRepository(User::class)->findAll() as $existingUser) {
            $existingUser->setRoles([]);
        }
        $entityManager->flush();

        $reporterToken = $this->loginAsUser($client);
        $admin = $this->createUser('very-secure-password', ['ROLE_ADMIN']);
        $adminToken = $this->loginExistingUser($client, $admin)['token'];
        $reportId = $this->createUserReport($client, $reporterToken, $admin);

        $client->jsonRequest('PATCH', '/api/admin/reports/'.$reportId, [
            'status' => 'resolved',
            'moderationStatus' => 'removed',
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$adminToken,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('conflict', $this->jsonResponse($client)['error']['code']);
        $entityManager->clear();
        $storedAdmin = $entityManager->find(User::class, $admin->getId());
        $storedReport = $entityManager->find(Report::class, $reportId);
        self::assertInstanceOf(User::class, $storedAdmin);
        self::assertInstanceOf(Report::class, $storedReport);
        self::assertNull($storedAdmin->getDeletedAt());
        self::assertSame('open', $storedReport->getStatus()->value);
    }

    public function testAdminReportListRejectsInvalidPaginationParameters(): void
    {
        $client = static::createClient();
        $this->clearReportsAndContent();
        $adminToken = $this->loginAsUser($client, roles: ['ROLE_ADMIN']);

        $client->request('GET', '/api/admin/reports?pageSize=101', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$adminToken,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertSame('pageSize must be between 1 and 100.', $this->jsonResponse($client)['error']['message']);
    }

    public function testMinorCannotReportAlcoholicRecipe(): void
    {
        $client = static::createClient();
        $this->clearReportsAndContent();
        $token = $this->loginAsUser($client, birthDate: new \DateTimeImmutable('2012-01-01'));
        $recipe = $this->createRecipe(RecipeStatus::Published, containsAlcohol: true);

        $client->jsonRequest('POST', '/api/reports', [
            'targetType' => 'recipe',
            'targetId' => $recipe->getId(),
            'reason' => 'wrong_alcohol_classification',
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    private function createRecipeReport(KernelBrowser $client, string $token, Recipe $recipe): int
    {
        $client->jsonRequest('POST', '/api/reports', [
            'targetType' => 'recipe',
            'targetId' => $recipe->getId(),
            'reason' => 'spam',
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $payload = $this->jsonResponse($client);
        self::assertIsInt($payload['id']);

        return $payload['id'];
    }

    private function createCommentReport(KernelBrowser $client, string $token, Comment $comment): int
    {
        $client->jsonRequest('POST', '/api/reports', [
            'targetType' => 'comment',
            'targetId' => $comment->getId(),
            'reason' => 'abuse',
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $payload = $this->jsonResponse($client);
        self::assertIsInt($payload['id']);

        return $payload['id'];
    }

    private function createUserReport(KernelBrowser $client, string $token, User $user): int
    {
        $client->jsonRequest('POST', '/api/reports', [
            'targetType' => 'user',
            'targetId' => $user->getId(),
            'reason' => 'abuse',
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
    private function createUser(string $password, array $roles = [], ?\DateTimeImmutable $birthDate = null): User
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $suffix = bin2hex(random_bytes(6));

        $user = new User(
            sprintf('report-api-%s@example.com', $suffix),
            sprintf('report_api_%s', $suffix),
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
        $recipe->setTitle(sprintf('Report API Recipe %s', $suffix));
        $recipe->setDescription('Recipe used to test report API endpoints.');
        $recipe->setStatus($status);
        $recipe->setContainsAlcoholComputed($containsAlcohol);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($recipe);
        $entityManager->flush();

        return $recipe;
    }

    private function createComment(Recipe $recipe): Comment
    {
        $author = $this->createUser('very-secure-password');
        $comment = new Comment($recipe, $author);
        $comment->setMessage('Comment to report.');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($comment);
        $entityManager->flush();

        return $comment;
    }

    private function clearReportsAndContent(): void
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

    private function assertValidationError(KernelBrowser $client): void
    {
        $payload = $this->jsonResponse($client);

        self::assertSame(422, $payload['error']['status']);
        self::assertSame('validation_failed', $payload['error']['code']);
        self::assertSame('Validation failed.', $payload['error']['message']);
        self::assertNotEmpty($payload['error']['violations']);
    }
}
