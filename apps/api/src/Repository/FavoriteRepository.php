<?php

namespace App\Repository;

use App\Entity\Favorite;
use App\Entity\Recipe;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Favorite>
 */
final class FavoriteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Favorite::class);
    }

    public function findOneForUserAndRecipe(User $user, Recipe $recipe): ?Favorite
    {
        return $this->findOneBy([
            'user' => $user,
            'recipe' => $recipe,
        ]);
    }
}
