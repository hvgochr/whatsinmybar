<?php

namespace App\Controller;

use App\Entity\Recipe;
use App\Repository\RecipeRepository;
use App\Security\RecipeAccess;
use App\Service\Upload\RecipeImageStorageInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class RecipeImageController extends AbstractController
{
    #[Route('/api/recipes/{slug}/image', name: 'api_recipe_image_upload', methods: ['POST'])]
    public function upload(
        string $slug,
        Request $request,
        RecipeRepository $recipeRepository,
        RecipeImageStorageInterface $recipeImageStorage,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $recipe = $this->findRecipe($slug, $recipeRepository);
        $this->denyAccessUnlessGranted(RecipeAccess::Manage, $recipe);

        $image = $request->files->get('image');
        if (!$image instanceof UploadedFile) {
            throw new BadRequestHttpException('Recipe image file is required.');
        }

        $recipe->setImagePath($recipeImageStorage->store($image));
        $entityManager->flush();

        return $this->json($this->payload($recipe));
    }

    #[Route('/api/recipes/{slug}/image', name: 'api_recipe_image_delete', methods: ['DELETE'])]
    public function delete(string $slug, RecipeRepository $recipeRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $recipe = $this->findRecipe($slug, $recipeRepository);
        $this->denyAccessUnlessGranted(RecipeAccess::Manage, $recipe);

        $recipe->setImagePath(null);
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
            'recipeSlug' => $recipe->getSlug(),
            'imagePath' => $recipe->getImagePath(),
        ];
    }
}
