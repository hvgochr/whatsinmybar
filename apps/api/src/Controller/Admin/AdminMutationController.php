<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Entity\Ingredient;
use App\Entity\Recipe;
use App\Entity\User;
use App\Enum\RecipeModerationStatus;
use App\Enum\RecipeStatus;
use App\Repository\CategoryRepository;
use App\Repository\IngredientRepository;
use App\Repository\RecipeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AdminMutationController extends AbstractController
{
    #[Route('/api/admin/users/{id}', name: 'api_admin_users_update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function updateUser(User $user, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $payload = $this->decodeJson($request);

        if (array_key_exists('roles', $payload)) {
            $user->setRoles($this->roles($payload['roles']));
        }

        if (array_key_exists('deleted', $payload)) {
            $user->setDeletedAt($this->boolean($payload['deleted'], 'deleted') ? new \DateTimeImmutable() : null);
        }

        $entityManager->flush();

        return $this->json($this->userPayload($user));
    }

    #[Route('/api/admin/recipes/{slug}', name: 'api_admin_recipes_update', methods: ['PATCH'])]
    public function updateRecipe(string $slug, Request $request, RecipeRepository $recipeRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $recipe = $recipeRepository->findOneBy(['slug' => $slug]);
        if (!$recipe instanceof Recipe) {
            throw $this->createNotFoundException('Recipe not found.');
        }

        $payload = $this->decodeJson($request);

        if (array_key_exists('status', $payload)) {
            $recipe->setStatus(RecipeStatus::tryFrom((string) $payload['status']) ?? throw new BadRequestHttpException('Invalid recipe status.'));
        }

        if (array_key_exists('moderationStatus', $payload)) {
            $recipe->setModerationStatus(RecipeModerationStatus::tryFrom((string) $payload['moderationStatus']) ?? throw new BadRequestHttpException('Invalid moderation status.'));
        }

        if (array_key_exists('containsAlcoholOverride', $payload)) {
            $recipe->setContainsAlcoholOverride(null === $payload['containsAlcoholOverride'] ? null : $this->boolean($payload['containsAlcoholOverride'], 'containsAlcoholOverride'));
        }

        if (array_key_exists('deleted', $payload) && $this->boolean($payload['deleted'], 'deleted') && null === $recipe->getDeletedAt()) {
            $recipe->softDelete();
        }

        $entityManager->flush();

        return $this->json($this->recipePayload($recipe));
    }

    #[Route('/api/admin/categories', name: 'api_admin_categories_create', methods: ['POST'])]
    public function createCategory(Request $request, ValidatorInterface $validator, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $category = new Category();
        $this->applyCategoryPayload($category, $this->decodeJson($request));

        $violations = $validator->validate($category);
        if ($violations->count() > 0) {
            return $this->validationErrorResponse($violations);
        }

        $entityManager->persist($category);
        $entityManager->flush();

        return $this->json($this->categoryPayload($category), JsonResponse::HTTP_CREATED);
    }

    #[Route('/api/admin/categories/{slug}', name: 'api_admin_categories_update', methods: ['PATCH'])]
    public function updateCategory(string $slug, Request $request, CategoryRepository $categoryRepository, ValidatorInterface $validator, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $category = $categoryRepository->findOneBy(['slug' => $slug]);
        if (!$category instanceof Category) {
            throw $this->createNotFoundException('Category not found.');
        }

        $this->applyCategoryPayload($category, $this->decodeJson($request), partial: true);

        $violations = $validator->validate($category);
        if ($violations->count() > 0) {
            return $this->validationErrorResponse($violations);
        }

        $entityManager->flush();

        return $this->json($this->categoryPayload($category));
    }

    #[Route('/api/admin/ingredients', name: 'api_admin_ingredients_create', methods: ['POST'])]
    public function createIngredient(Request $request, ValidatorInterface $validator, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $ingredient = new Ingredient();
        $this->applyIngredientPayload($ingredient, $this->decodeJson($request));

        $violations = $validator->validate($ingredient);
        if ($violations->count() > 0) {
            return $this->validationErrorResponse($violations);
        }

        $entityManager->persist($ingredient);
        $entityManager->flush();

        return $this->json($this->ingredientPayload($ingredient), JsonResponse::HTTP_CREATED);
    }

    #[Route('/api/admin/ingredients/{slug}', name: 'api_admin_ingredients_update', methods: ['PATCH'])]
    public function updateIngredient(string $slug, Request $request, IngredientRepository $ingredientRepository, ValidatorInterface $validator, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $ingredient = $ingredientRepository->findOneBy(['slug' => $slug]);
        if (!$ingredient instanceof Ingredient) {
            throw $this->createNotFoundException('Ingredient not found.');
        }

        $this->applyIngredientPayload($ingredient, $this->decodeJson($request), partial: true);

        $violations = $validator->validate($ingredient);
        if ($violations->count() > 0) {
            return $this->validationErrorResponse($violations);
        }

        $entityManager->flush();

        return $this->json($this->ingredientPayload($ingredient));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyCategoryPayload(Category $category, array $payload, bool $partial = false): void
    {
        if (!$partial && !array_key_exists('name', $payload)) {
            throw new BadRequestHttpException('Category name is required.');
        }

        if (array_key_exists('name', $payload)) {
            $category->setName((string) $payload['name']);
        }

        if (array_key_exists('slug', $payload)) {
            $category->setSlug((string) $payload['slug']);
        }

        if (array_key_exists('description', $payload)) {
            $category->setDescription(null === $payload['description'] ? null : (string) $payload['description']);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyIngredientPayload(Ingredient $ingredient, array $payload, bool $partial = false): void
    {
        if (!$partial && !array_key_exists('name', $payload)) {
            throw new BadRequestHttpException('Ingredient name is required.');
        }

        if (array_key_exists('name', $payload)) {
            $ingredient->setName((string) $payload['name']);
        }

        if (array_key_exists('slug', $payload)) {
            $ingredient->setSlug((string) $payload['slug']);
        }

        if (array_key_exists('containsAlcohol', $payload)) {
            $ingredient->setContainsAlcohol($this->boolean($payload['containsAlcohol'], 'containsAlcohol'));
        }
    }

    /**
     * @return list<string>
     */
    private function roles(mixed $roles): array
    {
        if (!is_array($roles) || !array_is_list($roles)) {
            throw new BadRequestHttpException('roles must be a list.');
        }

        $cleanRoles = [];
        foreach ($roles as $role) {
            if (!is_string($role) || !preg_match('/^ROLE_[A-Z_]+$/', $role)) {
                throw new BadRequestHttpException('roles must contain valid role names.');
            }

            if ('ROLE_USER' !== $role) {
                $cleanRoles[] = $role;
            }
        }

        return array_values(array_unique($cleanRoles));
    }

    private function boolean(mixed $value, string $field): bool
    {
        if (!is_bool($value)) {
            throw new BadRequestHttpException(sprintf('%s must be a boolean.', $field));
        }

        return $value;
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

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'username' => $user->getUsername(),
            'roles' => $user->getRoles(),
            'deleted' => null !== $user->getDeletedAt(),
            'deletedAt' => $user->getDeletedAt()?->format(DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function recipePayload(Recipe $recipe): array
    {
        return [
            'id' => $recipe->getId(),
            'title' => $recipe->getTitle(),
            'slug' => $recipe->getSlug(),
            'status' => $recipe->getStatus()->value,
            'moderationStatus' => $recipe->getModerationStatus()->value,
            'containsAlcoholOverride' => $recipe->getContainsAlcoholOverride(),
            'deleted' => null !== $recipe->getDeletedAt(),
            'deletedAt' => $recipe->getDeletedAt()?->format(DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryPayload(Category $category): array
    {
        return [
            'id' => $category->getId(),
            'name' => $category->getName(),
            'slug' => $category->getSlug(),
            'description' => $category->getDescription(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ingredientPayload(Ingredient $ingredient): array
    {
        return [
            'id' => $ingredient->getId(),
            'name' => $ingredient->getName(),
            'slug' => $ingredient->getSlug(),
            'containsAlcohol' => $ingredient->containsAlcohol(),
        ];
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
