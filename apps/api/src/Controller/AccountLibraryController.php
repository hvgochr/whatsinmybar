<?php

namespace App\Controller;

use App\Entity\Recipe;
use App\Entity\User;
use App\Pagination\PageRequest;
use App\Pagination\PaginatedResponse;
use App\Repository\FavoriteRepository;
use App\Repository\RecipeRepository;
use App\Security\AlcoholAccessPolicy;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class AccountLibraryController extends AbstractController
{
    #[Route('/api/me/recipes', name: 'api_account_recipes', methods: ['GET'])]
    public function owned(
        Request $request,
        #[CurrentUser] ?User $user,
        RecipeRepository $recipeRepository,
        FavoriteRepository $favoriteRepository,
        AlcoholAccessPolicy $alcoholAccessPolicy,
    ): JsonResponse {
        $user = $this->authenticatedUser($user);
        $pagination = PageRequest::fromRequest($request);
        $result = $recipeRepository->paginateOwnedBy($user, $pagination, $alcoholAccessPolicy->canAccessAlcohol($user));
        $favoriteIds = array_fill_keys($favoriteRepository->findRecipeIdsForUser($user, $result->items), true);

        return $this->json(PaginatedResponse::from(
            $result,
            $pagination,
            fn (Recipe $recipe): array => $this->recipePayload($recipe, isset($favoriteIds[$recipe->getId()])),
        ));
    }

    #[Route('/api/me/saved-recipes', name: 'api_account_saved_recipes', methods: ['GET'])]
    public function saved(
        Request $request,
        #[CurrentUser] ?User $user,
        RecipeRepository $recipeRepository,
        AlcoholAccessPolicy $alcoholAccessPolicy,
    ): JsonResponse {
        $user = $this->authenticatedUser($user);
        $pagination = PageRequest::fromRequest($request);

        return $this->json(PaginatedResponse::from(
            $recipeRepository->paginateSavedBy($user, $pagination, $alcoholAccessPolicy->canAccessAlcohol($user)),
            $pagination,
            fn (Recipe $recipe): array => $this->recipePayload($recipe, true),
        ));
    }

    private function authenticatedUser(?User $user): User
    {
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentication required.');
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function recipePayload(Recipe $recipe, bool $favorited): array
    {
        return [
            'id' => $recipe->getId(),
            'authorUsername' => $recipe->getAuthorUsername(),
            'title' => $recipe->getTitle(),
            'slug' => $recipe->getSlug(),
            'description' => $recipe->getDescription(),
            'difficulty' => $recipe->getDifficulty()->value,
            'preparationTimeMinutes' => $recipe->getPreparationTimeMinutes(),
            'servings' => $recipe->getServings(),
            'containsAlcohol' => $recipe->containsAlcohol(),
            'imagePath' => $recipe->getImagePath(),
            'status' => $recipe->getStatus()->value,
            'moderationStatus' => $recipe->getModerationStatus()->value,
            'favoriteCount' => $recipe->getFavoriteCount(),
            'favorited' => $favorited,
            'publishedAt' => $recipe->getPublishedAt()?->format(DATE_ATOM),
            'createdAt' => $recipe->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $recipe->getUpdatedAt()->format(DATE_ATOM),
        ];
    }
}
