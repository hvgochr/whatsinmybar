<?php

namespace App\Controller;

use App\Entity\Recipe;
use App\Entity\User;
use App\Enum\RecipeStatus;
use App\Repository\RecipeRepository;
use App\Security\RecipeAccess;
use App\Service\FavoriteManager;
use App\Service\FavoriteResult;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class FavoriteController extends AbstractController
{
    #[Route('/api/recipes/{slug}/favorite', name: 'api_recipe_favorite_add', methods: ['POST'])]
    public function add(
        string $slug,
        #[CurrentUser] ?User $user,
        RecipeRepository $recipeRepository,
        FavoriteManager $favoriteManager,
    ): JsonResponse {
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentication required.');
        }

        $recipe = $this->findPublishedVisibleRecipe($slug, $user, $recipeRepository);

        return $this->json($this->payload($favoriteManager->add($user, $recipe)));
    }

    #[Route('/api/recipes/{slug}/favorite', name: 'api_recipe_favorite_remove', methods: ['DELETE'])]
    public function remove(
        string $slug,
        #[CurrentUser] ?User $user,
        RecipeRepository $recipeRepository,
        FavoriteManager $favoriteManager,
    ): JsonResponse {
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentication required.');
        }

        $recipe = $this->findPublishedVisibleRecipe($slug, $user, $recipeRepository);

        return $this->json($this->payload($favoriteManager->remove($user, $recipe)));
    }

    private function findPublishedVisibleRecipe(string $slug, User $user, RecipeRepository $recipeRepository): Recipe
    {
        $recipe = $recipeRepository->findOneBy(['slug' => $slug]);
        if (!$recipe instanceof Recipe || null !== $recipe->getDeletedAt()) {
            throw $this->createNotFoundException('Recipe not found.');
        }

        $this->denyAccessUnlessGranted(RecipeAccess::View, $recipe);

        if (RecipeStatus::Published !== $recipe->getStatus()) {
            throw $this->createAccessDeniedException('Only published recipes can be favorited.');
        }

        return $recipe;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(FavoriteResult $result): array
    {
        return [
            'recipeSlug' => $result->recipe->getSlug(),
            'favoriteCount' => $result->favoriteCount,
            'favorited' => $result->favorited,
            'changed' => $result->changed,
        ];
    }
}
