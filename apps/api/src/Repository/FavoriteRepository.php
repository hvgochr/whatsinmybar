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

    /**
     * @param list<Recipe> $recipes
     *
     * @return list<int>
     */
    public function findRecipeIdsForUser(User $user, array $recipes): array
    {
        if ([] === $recipes) {
            return [];
        }

        $rows = $this->createQueryBuilder('favorite')
            ->select('IDENTITY(favorite.recipe) AS recipeId')
            ->andWhere('favorite.user = :user')
            ->andWhere('favorite.recipe IN (:recipes)')
            ->setParameter('user', $user)
            ->setParameter('recipes', $recipes)
            ->getQuery()
            ->getScalarResult()
        ;

        return array_map(static fn (array $row): int => (int) $row['recipeId'], $rows);
    }
}
