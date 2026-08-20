<?php

namespace App\Repository;

use App\Entity\Ingredient;
use App\Entity\Recipe;
use App\Pagination\PageRequest;
use App\Pagination\PageResult;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends PaginatedRepository<Recipe>
 */
final class RecipeRepository extends PaginatedRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Recipe::class);
    }

    /**
     * @return PageResult<Recipe>
     */
    public function paginateLatestForAdmin(PageRequest $pagination): PageResult
    {
        $query = $this->createQueryBuilder('recipe')
            ->leftJoin('recipe.author', 'author')
            ->addSelect('author')
            ->orderBy('recipe.updatedAt', 'DESC')
            ->addOrderBy('recipe.id', 'DESC')
            ->getQuery()
        ;

        return $this->paginate($query, $pagination);
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
