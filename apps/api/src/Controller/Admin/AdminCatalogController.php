<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Entity\Ingredient;
use App\Entity\Recipe;
use App\Entity\User;
use App\Pagination\AdminPage;
use App\Pagination\AdminPagination;
use App\Repository\CategoryRepository;
use App\Repository\IngredientRepository;
use App\Repository\RecipeRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class AdminCatalogController extends AbstractController
{
    #[Route('/api/admin/users', name: 'api_admin_users_list', methods: ['GET'])]
    public function users(Request $request, UserRepository $userRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $pagination = AdminPagination::fromRequest($request);

        return $this->json($this->pagePayload(
            $userRepository->paginateLatestForAdmin($pagination),
            $pagination,
            fn (User $user): array => $this->userPayload($user),
        ));
    }

    #[Route('/api/admin/recipes', name: 'api_admin_recipes_list', methods: ['GET'])]
    public function recipes(Request $request, RecipeRepository $recipeRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $pagination = AdminPagination::fromRequest($request);

        return $this->json($this->pagePayload(
            $recipeRepository->paginateLatestForAdmin($pagination),
            $pagination,
            fn (Recipe $recipe): array => $this->recipePayload($recipe),
        ));
    }

    #[Route('/api/admin/categories', name: 'api_admin_categories_list', methods: ['GET'])]
    public function categories(Request $request, CategoryRepository $categoryRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $pagination = AdminPagination::fromRequest($request);

        return $this->json($this->pagePayload(
            $categoryRepository->paginateLatestForAdmin($pagination),
            $pagination,
            fn (Category $category): array => $this->categoryPayload($category),
        ));
    }

    #[Route('/api/admin/ingredients', name: 'api_admin_ingredients_list', methods: ['GET'])]
    public function ingredients(Request $request, IngredientRepository $ingredientRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $pagination = AdminPagination::fromRequest($request);

        return $this->json($this->pagePayload(
            $ingredientRepository->paginateLatestForAdmin($pagination),
            $pagination,
            fn (Ingredient $ingredient): array => $this->ingredientPayload($ingredient),
        ));
    }

    /**
     * @template T of object
     *
     * @param AdminPage<T>                      $page
     * @param callable(T): array<string, mixed> $payload
     *
     * @return array{items: list<array<string, mixed>>, page: int, pageSize: int, totalItems: int, totalPages: int}
     */
    private function pagePayload(AdminPage $page, AdminPagination $pagination, callable $payload): array
    {
        return [
            'items' => array_map($payload, $page->items),
            'page' => $pagination->page,
            'pageSize' => $pagination->pageSize,
            'totalItems' => $page->totalItems,
            'totalPages' => $pagination->totalPages($page->totalItems),
        ];
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
            'birthDate' => $user->getBirthDate()->format('Y-m-d'),
            'bio' => $user->getBio(),
            'avatarPath' => $user->getAvatarPath(),
            'roles' => $user->getRoles(),
            'deleted' => null !== $user->getDeletedAt(),
            'deletedAt' => $user->getDeletedAt()?->format(DATE_ATOM),
            'createdAt' => $user->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $user->getUpdatedAt()->format(DATE_ATOM),
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
            'authorUsername' => $recipe->getAuthorUsername(),
            'status' => $recipe->getStatus()->value,
            'moderationStatus' => $recipe->getModerationStatus()->value,
            'containsAlcohol' => $recipe->containsAlcohol(),
            'containsAlcoholOverride' => $recipe->getContainsAlcoholOverride(),
            'favoriteCount' => $recipe->getFavoriteCount(),
            'deleted' => null !== $recipe->getDeletedAt(),
            'deletedAt' => $recipe->getDeletedAt()?->format(DATE_ATOM),
            'publishedAt' => $recipe->getPublishedAt()?->format(DATE_ATOM),
            'createdAt' => $recipe->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $recipe->getUpdatedAt()->format(DATE_ATOM),
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
            'createdAt' => $category->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $category->getUpdatedAt()->format(DATE_ATOM),
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
            'createdAt' => $ingredient->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $ingredient->getUpdatedAt()->format(DATE_ATOM),
        ];
    }
}
