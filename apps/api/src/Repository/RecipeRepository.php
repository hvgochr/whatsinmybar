<?php

namespace App\Repository;

use App\Entity\Ingredient;
use App\Entity\Recipe;
use App\Entity\User;
use App\Enum\RecipeModerationStatus;
use App\Enum\RecipeStatus;
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
     * @return PageResult<Recipe>
     */
    public function paginateOwnedBy(User $user, PageRequest $pagination, bool $canAccessAlcohol): PageResult
    {
        $queryBuilder = $this->createQueryBuilder('recipe')
            ->andWhere('recipe.author = :user')
            ->andWhere('recipe.deletedAt IS NULL')
            ->andWhere('recipe.moderationStatus = :moderationStatus')
            ->setParameter('user', $user)
            ->setParameter('moderationStatus', RecipeModerationStatus::Visible)
            ->orderBy('recipe.updatedAt', 'DESC')
            ->addOrderBy('recipe.id', 'DESC')
        ;

        $this->restrictAlcohol($queryBuilder, $canAccessAlcohol);

        return $this->paginate($queryBuilder->getQuery(), $pagination);
    }

    /**
     * @return PageResult<Recipe>
     */
    public function paginateSavedBy(User $user, PageRequest $pagination, bool $canAccessAlcohol): PageResult
    {
        $queryBuilder = $this->createQueryBuilder('recipe')
            ->innerJoin('recipe.favorites', 'favorite', 'WITH', 'favorite.user = :user')
            ->andWhere('recipe.deletedAt IS NULL')
            ->andWhere('recipe.status = :status')
            ->andWhere('recipe.moderationStatus = :moderationStatus')
            ->setParameter('user', $user)
            ->setParameter('status', RecipeStatus::Published)
            ->setParameter('moderationStatus', RecipeModerationStatus::Visible)
            ->orderBy('favorite.createdAt', 'DESC')
            ->addOrderBy('recipe.id', 'DESC')
        ;

        $this->restrictAlcohol($queryBuilder, $canAccessAlcohol);

        return $this->paginate($queryBuilder->getQuery(), $pagination);
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

    private function restrictAlcohol(\Doctrine\ORM\QueryBuilder $queryBuilder, bool $canAccessAlcohol): void
    {
        if ($canAccessAlcohol) {
            return;
        }

        $queryBuilder->andWhere(
            'recipe.containsAlcoholOverride = false OR (recipe.containsAlcoholOverride IS NULL AND recipe.containsAlcoholComputed = false)',
        );
    }
}
