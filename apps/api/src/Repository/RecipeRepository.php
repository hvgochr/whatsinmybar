<?php

namespace App\Repository;

use App\Entity\Ingredient;
use App\Entity\Recipe;
use App\Pagination\AdminPage;
use App\Pagination\AdminPagination;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
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
     * @return AdminPage<Recipe>
     */
    public function paginateLatestForAdmin(AdminPagination $pagination): AdminPage
    {
        $query = $this->createQueryBuilder('recipe')
            ->leftJoin('recipe.author', 'author')
            ->addSelect('author')
            ->orderBy('recipe.updatedAt', 'DESC')
            ->addOrderBy('recipe.id', 'DESC')
            ->setFirstResult($pagination->offset())
            ->setMaxResults($pagination->pageSize)
            ->getQuery()
        ;
        $paginator = new Paginator($query, fetchJoinCollection: false);

        return new AdminPage(array_values(iterator_to_array($paginator)), count($paginator));
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
