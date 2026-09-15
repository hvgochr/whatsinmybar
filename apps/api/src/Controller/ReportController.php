<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Recipe;
use App\Entity\Report;
use App\Entity\User;
use App\Enum\CommentModerationStatus;
use App\Enum\RecipeModerationStatus;
use App\Enum\ReportReason;
use App\Enum\ReportStatus;
use App\Enum\ReportTargetType;
use App\Pagination\PageRequest;
use App\Pagination\PaginatedResponse;
use App\Repository\CommentRepository;
use App\Repository\RecipeRepository;
use App\Repository\ReportRepository;
use App\Repository\UserRepository;
use App\Security\RecipeAccess;
use App\Service\ActiveAdminGuard;
use App\Service\RecipePublicationValidator;
use App\Service\ReportTargetContextProvider;
use App\Service\UserAccountAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ReportController extends AbstractController
{
    public function __construct(
        private readonly RecipePublicationValidator $publicationValidator,
        private readonly ReportTargetContextProvider $targetContextProvider,
    ) {
    }

    #[Route('/api/reports', name: 'api_reports_create', methods: ['POST'])]
    public function create(
        Request $request,
        #[CurrentUser] ?User $user,
        RecipeRepository $recipeRepository,
        CommentRepository $commentRepository,
        UserRepository $userRepository,
        ValidatorInterface $validator,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentication required.');
        }

        $payload = $this->decodeJson($request);
        $violations = $validator->validate($payload, new Assert\Collection(
            fields: [
                'targetType' => new Assert\Required([
                    new Assert\NotNull(),
                    new Assert\Type('string'),
                    new Assert\NotBlank(),
                ]),
                'targetId' => new Assert\Required([
                    new Assert\NotNull(),
                    new Assert\Type('integer'),
                    new Assert\Positive(),
                ]),
                'reason' => new Assert\Required([
                    new Assert\NotNull(),
                    new Assert\Type('string'),
                    new Assert\NotBlank(),
                ]),
                'message' => new Assert\Optional([
                    new Assert\Type('string'),
                ]),
            ],
            allowExtraFields: false,
        ));

        if ($violations->count() > 0) {
            return $this->validationErrorResponse($violations);
        }

        $targetType = $this->targetType((string) ($payload['targetType'] ?? ''));
        $targetId = (int) ($payload['targetId'] ?? 0);
        $reason = $this->reason((string) ($payload['reason'] ?? ''));

        $this->assertTargetCanBeReported($targetType, $targetId, $recipeRepository, $commentRepository, $userRepository);

        $report = new Report($user, $targetType, $targetId, $reason);
        $report->setMessage(isset($payload['message']) ? (string) $payload['message'] : null);

        $violations = $validator->validate($report);
        if ($violations->count() > 0) {
            return $this->validationErrorResponse($violations);
        }

        $entityManager->persist($report);
        $entityManager->flush();

        return $this->json($this->payload($report), JsonResponse::HTTP_CREATED);
    }

    #[Route('/api/admin/reports', name: 'api_admin_reports_list', methods: ['GET'])]
    public function list(Request $request, ReportRepository $reportRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $pagination = PageRequest::fromRequest($request);
        $page = $reportRepository->paginateLatestForAdmin($pagination);
        $contexts = $this->targetContextProvider->forReports($page->items);

        return $this->json(PaginatedResponse::from(
            $page,
            $pagination,
            fn (Report $report): array => $this->payload($report, $contexts[(int) $report->getId()] ?? null, true),
        ));
    }

    #[Route('/api/admin/reports/{id}', name: 'api_admin_reports_update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function update(
        Report $report,
        Request $request,
        #[CurrentUser] ?User $user,
        RecipeRepository $recipeRepository,
        CommentRepository $commentRepository,
        UserRepository $userRepository,
        UserAccountAccess $userAccountAccess,
        ActiveAdminGuard $activeAdminGuard,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentication required.');
        }

        $payload = $this->decodeJson($request);

        $entityManager->wrapInTransaction(function () use ($activeAdminGuard, $commentRepository, $entityManager, $payload, $recipeRepository, $report, $user, $userAccountAccess, $userRepository): void {
            if (array_key_exists('status', $payload)) {
                $report->review($this->status((string) $payload['status']), $user);
            }

            if (array_key_exists('moderationStatus', $payload)) {
                $this->applyModerationStatus($report, (string) $payload['moderationStatus'], $recipeRepository, $commentRepository, $userRepository, $userAccountAccess, $activeAdminGuard);
            }

            $entityManager->flush();
        });

        $context = $this->targetContextProvider->forReports([$report]);

        return $this->json($this->payload($report, $context[(int) $report->getId()] ?? null, true));
    }

    private function assertTargetCanBeReported(
        ReportTargetType $targetType,
        int $targetId,
        RecipeRepository $recipeRepository,
        CommentRepository $commentRepository,
        UserRepository $userRepository,
    ): void {
        if ($targetId <= 0) {
            throw new BadRequestHttpException('Report targetId must be positive.');
        }

        match ($targetType) {
            ReportTargetType::Recipe => $this->assertRecipeCanBeReported($targetId, $recipeRepository),
            ReportTargetType::Comment => $this->assertCommentCanBeReported($targetId, $commentRepository),
            ReportTargetType::User => $this->assertUserCanBeReported($targetId, $userRepository),
        };
    }

    private function assertRecipeCanBeReported(int $targetId, RecipeRepository $recipeRepository): void
    {
        $recipe = $recipeRepository->find($targetId);
        if (!$recipe instanceof Recipe || null !== $recipe->getDeletedAt()) {
            throw $this->createNotFoundException('Report target not found.');
        }

        $this->denyAccessUnlessGranted(RecipeAccess::View, $recipe);
    }

    private function assertCommentCanBeReported(int $targetId, CommentRepository $commentRepository): void
    {
        $comment = $commentRepository->find($targetId);
        if (!$comment instanceof Comment) {
            throw $this->createNotFoundException('Report target not found.');
        }

        $this->denyAccessUnlessGranted(RecipeAccess::View, $comment->getRecipe());
    }

    private function assertUserCanBeReported(int $targetId, UserRepository $userRepository): void
    {
        if (!$userRepository->find($targetId) instanceof User) {
            throw $this->createNotFoundException('Report target not found.');
        }
    }

    private function applyModerationStatus(
        Report $report,
        string $moderationStatus,
        RecipeRepository $recipeRepository,
        CommentRepository $commentRepository,
        UserRepository $userRepository,
        UserAccountAccess $userAccountAccess,
        ActiveAdminGuard $activeAdminGuard,
    ): void {
        match ($report->getTargetType()) {
            ReportTargetType::Recipe => $this->applyRecipeModerationStatus($report->getTargetId(), $moderationStatus, $recipeRepository),
            ReportTargetType::Comment => $this->applyCommentModerationStatus($report->getTargetId(), $moderationStatus, $commentRepository),
            ReportTargetType::User => $this->applyUserModerationStatus($report->getTargetId(), $moderationStatus, $userRepository, $userAccountAccess, $activeAdminGuard),
        };
    }

    private function applyRecipeModerationStatus(int $targetId, string $moderationStatus, RecipeRepository $recipeRepository): void
    {
        $recipe = $recipeRepository->find($targetId);
        if (!$recipe instanceof Recipe) {
            throw $this->createNotFoundException('Report target not found.');
        }

        $previousModerationStatus = $recipe->getModerationStatus();
        $recipe->setModerationStatus(RecipeModerationStatus::tryFrom($moderationStatus) ?? throw new BadRequestHttpException('Invalid moderation status.'));
        $this->publicationValidator->validateVisibilityTransition($recipe, $recipe->getStatus(), $previousModerationStatus);
    }

    private function applyCommentModerationStatus(int $targetId, string $moderationStatus, CommentRepository $commentRepository): void
    {
        $comment = $commentRepository->find($targetId);
        if (!$comment instanceof Comment) {
            throw $this->createNotFoundException('Report target not found.');
        }

        $comment->setModerationStatus(CommentModerationStatus::tryFrom($moderationStatus) ?? throw new BadRequestHttpException('Invalid moderation status.'));
    }

    private function applyUserModerationStatus(int $targetId, string $moderationStatus, UserRepository $userRepository, UserAccountAccess $userAccountAccess, ActiveAdminGuard $activeAdminGuard): void
    {
        $user = $userRepository->find($targetId);
        if (!$user instanceof User) {
            throw $this->createNotFoundException('Report target not found.');
        }

        $deleted = match ($moderationStatus) {
            RecipeModerationStatus::Visible->value => false,
            RecipeModerationStatus::Removed->value => true,
            default => throw new BadRequestHttpException('User moderation status must be visible or removed.'),
        };
        $activeAdminGuard->assertCanApply($user, $user->getRoles(), $deleted);
        $userAccountAccess->setDeleted($user, $deleted);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Report $report, ?array $targetContext = null, bool $includeTargetContext = false): array
    {
        $payload = [
            'id' => $report->getId(),
            'reporterUsername' => $report->getReporter()->getUsername(),
            'targetType' => $report->getTargetType()->value,
            'targetId' => $report->getTargetId(),
            'reason' => $report->getReason()->value,
            'message' => $report->getMessage(),
            'status' => $report->getStatus()->value,
            'reviewedByUsername' => $report->getReviewedBy()?->getUsername(),
            'reviewedAt' => $report->getReviewedAt()?->format(DATE_ATOM),
            'createdAt' => $report->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $report->getUpdatedAt()->format(DATE_ATOM),
        ];

        if ($includeTargetContext) {
            $payload['targetContext'] = $targetContext;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(Request $request): array
    {
        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new BadRequestHttpException('Invalid JSON body.');
        }

        if (!is_array($payload)) {
            throw new BadRequestHttpException('Expected a JSON object.');
        }

        return $payload;
    }

    private function targetType(string $value): ReportTargetType
    {
        return ReportTargetType::tryFrom($value) ?? throw new BadRequestHttpException('Invalid report target type.');
    }

    private function reason(string $value): ReportReason
    {
        return ReportReason::tryFrom($value) ?? throw new BadRequestHttpException('Invalid report reason.');
    }

    private function status(string $value): ReportStatus
    {
        return ReportStatus::tryFrom($value) ?? throw new BadRequestHttpException('Invalid report status.');
    }

    private function validationErrorResponse(ConstraintViolationListInterface $violations): JsonResponse
    {
        $errors = [];

        foreach ($violations as $violation) {
            $errors[] = [
                'property' => $violation->getPropertyPath(),
                'message' => $violation->getMessage(),
            ];
        }

        return $this->json([
            'error' => [
                'status' => JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
                'code' => 'validation_failed',
                'message' => 'Validation failed.',
                'violations' => $errors,
            ],
            'errors' => $errors,
        ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }
}
