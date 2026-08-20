<?php

namespace App\Repository;

use App\Entity\Ingredient;
use App\Pagination\PageRequest;
use App\Pagination\PageResult;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends PaginatedRepository<Ingredient>
 */
final class IngredientRepository extends PaginatedRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ingredient::class);
    }

    /**
     * @return PageResult<Ingredient>
     */
    public function paginateLatestForAdmin(PageRequest $pagination): PageResult
    {
        $query = $this->createQueryBuilder('ingredient')
            ->orderBy('ingredient.updatedAt', 'DESC')
            ->addOrderBy('ingredient.id', 'DESC')
            ->getQuery()
        ;

        return $this->paginate($query, $pagination);
    }
}
