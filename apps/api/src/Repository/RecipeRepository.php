<?php

namespace App\Repository;

use App\Entity\Ingredient;
use App\Entity\Recipe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Recipe>
 */
final class RecipeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Recipe::class);
    }

    /**
     * @return list<Recipe>
     */
    public function findLatestForAdmin(): array
    {
        return $this->createQueryBuilder('recipe')
            ->leftJoin('recipe.author', 'author')
            ->addSelect('author')
            ->orderBy('recipe.updatedAt', 'DESC')
            ->addOrderBy('recipe.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return list<Recipe>
     */
    public function findUsingIngredient(Ingredient $ingredient): array
    {
        return $this->createQueryBuilder('recipe')
            ->innerJoin('recipe.recipeIngredients', 'matchingRecipeIngredient', 'WITH', 'matchingRecipeIngredient.ingredient = :ingredient')
            ->leftJoin('recipe.recipeIngredients', 'recipeIngredient')
            ->addSelect('recipeIngredient')
            ->leftJoin('recipeIngredient.ingredient', 'ingredient')
            ->addSelect('ingredient')
            ->setParameter('ingredient', $ingredient)
            ->getQuery()
            ->getResult()
        ;
    }
}
