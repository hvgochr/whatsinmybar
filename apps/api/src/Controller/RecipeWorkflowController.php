<?php

namespace App\Controller;

use App\Entity\Recipe;
use App\Enum\RecipeStatus;
use App\Repository\RecipeRepository;
use App\Security\RecipeAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class RecipeWorkflowController extends AbstractController
{
    #[Route('/api/recipes/{slug}/publish', name: 'api_recipe_publish', methods: ['POST'])]
    public function publish(string $slug, RecipeRepository $recipeRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $recipe = $this->findRecipe($slug, $recipeRepository);
        $this->denyAccessUnlessGranted(RecipeAccess::Manage, $recipe);

        $recipe->setStatus(RecipeStatus::Published);
        $entityManager->flush();

        return $this->json($this->payload($recipe));
    }

    #[Route('/api/recipes/{slug}/archive', name: 'api_recipe_archive', methods: ['POST'])]
    public function archive(string $slug, RecipeRepository $recipeRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $recipe = $this->findRecipe($slug, $recipeRepository);
        $this->denyAccessUnlessGranted(RecipeAccess::Manage, $recipe);

        $recipe->setStatus(RecipeStatus::Archived);
        $entityManager->flush();

        return $this->json($this->payload($recipe));
    }

    private function findRecipe(string $slug, RecipeRepository $recipeRepository): Recipe
    {
        $recipe = $recipeRepository->findOneBy(['slug' => $slug]);
        if (!$recipe instanceof Recipe || null !== $recipe->getDeletedAt()) {
            throw $this->createNotFoundException('Recipe not found.');
        }

        return $recipe;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Recipe $recipe): array
    {
        return [
            'id' => $recipe->getId(),
            'title' => $recipe->getTitle(),
            'slug' => $recipe->getSlug(),
            'status' => $recipe->getStatus()->value,
            'moderationStatus' => $recipe->getModerationStatus()->value,
            'publishedAt' => $recipe->getPublishedAt()?->format(DATE_ATOM),
            'deleted' => null !== $recipe->getDeletedAt(),
            'deletedAt' => $recipe->getDeletedAt()?->format(DATE_ATOM),
            'updatedAt' => $recipe->getUpdatedAt()->format(DATE_ATOM),
        ];
    }
}
