<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Entity\Ingredient;
use App\Entity\Recipe;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\IngredientRepository;
use App\Repository\RecipeRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class AdminCatalogController extends AbstractController
{
    #[Route('/api/admin/users', name: 'api_admin_users_list', methods: ['GET'])]
    public function users(UserRepository $userRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->json([
            'items' => array_map(
                fn (User $user): array => $this->userPayload($user),
                $userRepository->findLatestForAdmin(),
            ),
        ]);
    }

    #[Route('/api/admin/recipes', name: 'api_admin_recipes_list', methods: ['GET'])]
    public function recipes(RecipeRepository $recipeRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->json([
            'items' => array_map(
                fn (Recipe $recipe): array => $this->recipePayload($recipe),
                $recipeRepository->findLatestForAdmin(),
            ),
        ]);
    }

    #[Route('/api/admin/categories', name: 'api_admin_categories_list', methods: ['GET'])]
    public function categories(CategoryRepository $categoryRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->json([
            'items' => array_map(
                fn (Category $category): array => $this->categoryPayload($category),
                $categoryRepository->findLatestForAdmin(),
            ),
        ]);
    }

    #[Route('/api/admin/ingredients', name: 'api_admin_ingredients_list', methods: ['GET'])]
    public function ingredients(IngredientRepository $ingredientRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->json([
            'items' => array_map(
                fn (Ingredient $ingredient): array => $this->ingredientPayload($ingredient),
                $ingredientRepository->findLatestForAdmin(),
            ),
        ]);
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
