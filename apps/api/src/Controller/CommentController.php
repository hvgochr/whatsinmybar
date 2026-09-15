<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Recipe;
use App\Entity\User;
use App\Enum\CommentModerationStatus;
use App\Enum\RecipeStatus;
use App\Pagination\PageRequest;
use App\Pagination\PaginatedResponse;
use App\Repository\CommentRepository;
use App\Repository\RecipeRepository;
use App\Security\RecipeAccess;
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

final class CommentController extends AbstractController
{
    private const MAX_DEPTH = 3;

    #[Route('/api/recipes/{slug}/comments', name: 'api_recipe_comments_list', methods: ['GET'])]
    public function list(string $slug, Request $request, RecipeRepository $recipeRepository, CommentRepository $commentRepository): JsonResponse
    {
        $recipe = $this->findRecipe($slug, $recipeRepository);
        $this->denyAccessUnlessGranted(RecipeAccess::View, $recipe);
        $pagination = PageRequest::fromRequest($request);
        $page = $commentRepository->paginateForRecipe($recipe, $pagination);
        $replyCounts = $commentRepository->replyCounts($page->items);

        return $this->json(PaginatedResponse::from(
            $page,
            $pagination,
            fn (Comment $comment): array => $this->payload($comment, $replyCounts[$comment->getId()] ?? 0),
        ));
    }

    #[Route('/api/recipes/{slug}/comments', name: 'api_recipe_comments_create', methods: ['POST'])]
    public function create(
        string $slug,
        Request $request,
        #[CurrentUser] ?User $user,
        RecipeRepository $recipeRepository,
        CommentRepository $commentRepository,
        ValidatorInterface $validator,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentication required.');
        }

        $recipe = $this->findRecipe($slug, $recipeRepository);
        $this->denyAccessUnlessGranted(RecipeAccess::View, $recipe);

        if (RecipeStatus::Published !== $recipe->getStatus()) {
            throw $this->createAccessDeniedException('Only published recipes can be commented.');
        }

        $payload = $this->decodeJson($request);
        $violations = $validator->validate($payload, new Assert\Collection(
            fields: [
                'message' => new Assert\Required([
                    new Assert\NotNull(),
                    new Assert\Type('string'),
                    new Assert\NotBlank(),
                ]),
                'parentId' => new Assert\Optional([
                    new Assert\Type('integer'),
                    new Assert\Positive(),
                ]),
            ],
            allowExtraFields: false,
        ));

        if ($violations->count() > 0) {
            return $this->validationErrorResponse($violations);
        }

        $comment = new Comment($recipe, $user);
        $comment->setMessage((string) $payload['message']);

        if (isset($payload['parentId'])) {
            $parent = $commentRepository->find((int) $payload['parentId']);
            if (!$parent instanceof Comment || $parent->getRecipe() !== $recipe) {
                throw new BadRequestHttpException('Parent comment must belong to the same recipe.');
            }
            if ($parent->getDepth() >= self::MAX_DEPTH) {
                return $this->validationErrorsResponse([
                    ['property' => '[parentId]', 'message' => sprintf('Comments cannot be nested deeper than %d levels.', self::MAX_DEPTH)],
                ]);
            }

            $comment->setParent($parent);
        }

        $violations = $validator->validate($comment);
        if ($violations->count() > 0) {
            return $this->validationErrorResponse($violations);
        }

        $entityManager->persist($comment);
        $entityManager->flush();

        return $this->json($this->payload($comment), JsonResponse::HTTP_CREATED);
    }

    #[Route('/api/comments/{id}', name: 'api_comments_update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function update(
        Comment $comment,
        Request $request,
        #[CurrentUser] ?User $user,
        ValidatorInterface $validator,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $this->denyUnlessCommentCanBeManaged($comment, $user);

        if (null !== $comment->getDeletedAt()) {
            throw $this->createAccessDeniedException('Deleted comments cannot be edited.');
        }

        $payload = $this->decodeJson($request);
        $violations = $validator->validate($payload, new Assert\Collection(
            fields: [
                'message' => new Assert\Optional([
                    new Assert\NotNull(),
                    new Assert\Type('string'),
                    new Assert\NotBlank(),
                ]),
                'moderationStatus' => new Assert\Optional([
                    new Assert\NotNull(),
                    new Assert\Type('string'),
                ]),
            ],
            allowExtraFields: false,
            allowMissingFields: true,
        ));

        if ($violations->count() > 0) {
            return $this->validationErrorResponse($violations);
        }

        if (array_key_exists('message', $payload)) {
            $comment->setMessage((string) $payload['message']);
        }

        if (array_key_exists('moderationStatus', $payload)) {
            if (!$this->isGranted('ROLE_ADMIN')) {
                throw $this->createAccessDeniedException('Only admins can update moderation status.');
            }

            $comment->setModerationStatus($this->moderationStatus((string) $payload['moderationStatus']));
        }

        $violations = $validator->validate($comment);
        if ($violations->count() > 0) {
            return $this->validationErrorResponse($violations);
        }

        $entityManager->flush();

        return $this->json($this->payload($comment));
    }

    #[Route('/api/comments/{id}', name: 'api_comments_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(Comment $comment, #[CurrentUser] ?User $user, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyUnlessCommentCanBeManaged($comment, $user);

        if (null === $comment->getDeletedAt()) {
            $comment->softDelete();
            $entityManager->flush();
        }

        return $this->json($this->payload($comment));
    }

    private function findRecipe(string $slug, RecipeRepository $recipeRepository): Recipe
    {
        $recipe = $recipeRepository->findOneBy(['slug' => $slug]);
        if (!$recipe instanceof Recipe || null !== $recipe->getDeletedAt()) {
            throw $this->createNotFoundException('Recipe not found.');
        }

        return $recipe;
    }

    private function denyUnlessCommentCanBeManaged(Comment $comment, ?User $user): void
    {
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentication required.');
        }

        $this->denyAccessUnlessGranted(RecipeAccess::View, $comment->getRecipe());

        if (!$this->isGranted('ROLE_ADMIN') && !$comment->isAuthor($user)) {
            throw $this->createAccessDeniedException('Only the author or an admin can manage this comment.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Comment $comment, ?int $replyCount = null): array
    {
        return [
            'id' => $comment->getId(),
            'recipeSlug' => $comment->getRecipe()->getSlug(),
            'authorUsername' => $comment->getAuthor()->getUsername(),
            'authorAvatarPath' => $comment->getAuthor()->getAvatarPath(),
            'parentId' => $comment->getParent()?->getId(),
            'depth' => $comment->getDepth(),
            'canReply' => $comment->getDepth() < self::MAX_DEPTH,
            'message' => $comment->getPublicMessage(),
            'moderationStatus' => $comment->getModerationStatus()->value,
            'replyCount' => $replyCount ?? $comment->getReplies()->count(),
            'deleted' => null !== $comment->getDeletedAt(),
            'createdAt' => $comment->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $comment->getUpdatedAt()->format(DATE_ATOM),
        ];
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

    private function moderationStatus(string $value): CommentModerationStatus
    {
        return CommentModerationStatus::tryFrom($value) ?? throw new BadRequestHttpException('Invalid moderation status.');
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

        return $this->validationErrorsResponse($errors);
    }

    /**
     * @param list<array{property: string, message: string}> $errors
     */
    private function validationErrorsResponse(array $errors): JsonResponse
    {
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
