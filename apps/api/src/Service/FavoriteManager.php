<?php

namespace App\Service;

use App\Entity\Favorite;
use App\Entity\Recipe;
use App\Entity\User;
use App\Repository\FavoriteRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class FavoriteManager
{
    public function __construct(
        private FavoriteRepository $favoriteRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function add(User $user, Recipe $recipe): FavoriteResult
    {
        $favorite = $this->favoriteRepository->findOneForUserAndRecipe($user, $recipe);
        if ($favorite instanceof Favorite) {
            return new FavoriteResult($recipe, true, false);
        }

        $favorite = new Favorite($user, $recipe);
        $recipe->incrementFavoriteCount();

        $this->entityManager->persist($favorite);
        $this->entityManager->flush();

        return new FavoriteResult($recipe, true, true);
    }

    public function remove(User $user, Recipe $recipe): FavoriteResult
    {
        $favorite = $this->favoriteRepository->findOneForUserAndRecipe($user, $recipe);
        if (!$favorite instanceof Favorite) {
            return new FavoriteResult($recipe, false, false);
        }

        $this->entityManager->remove($favorite);
        $recipe->decrementFavoriteCount();
        $this->entityManager->flush();

        return new FavoriteResult($recipe, false, true);
    }
}
