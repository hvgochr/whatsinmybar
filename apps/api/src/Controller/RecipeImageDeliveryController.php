<?php

namespace App\Controller;

use App\Entity\Recipe;
use App\Repository\RecipeRepository;
use App\Security\RecipeAccess;
use App\Service\Upload\RecipeImageStorageInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Attribute\Route;

final class RecipeImageDeliveryController extends AbstractController
{
    #[Route('/api/recipe-images/{filename}', name: 'api_recipe_image_file', requirements: ['filename' => '[a-f0-9]{32}\.(jpg|png|webp)'], methods: ['GET', 'HEAD'])]
    public function show(string $filename, RecipeRepository $recipes, RecipeImageStorageInterface $storage): BinaryFileResponse
    {
        $path = '/uploads/recipes/'.$filename;
        $recipe = $recipes->findOneBy(['imagePath' => $path]);
        if (!$recipe instanceof Recipe || !$this->isGranted(RecipeAccess::View, $recipe)) {
            throw $this->createNotFoundException();
        }
        $local = $storage->localPath($path);
        if (null === $local || !is_file($local)) {
            throw $this->createNotFoundException();
        }
        $response = new BinaryFileResponse($local);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Type', match (pathinfo($filename, PATHINFO_EXTENSION)) {
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            default => 'image/webp',
        });

        return $response;
    }
}
