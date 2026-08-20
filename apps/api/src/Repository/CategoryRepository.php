<?php

namespace App\Repository;

use App\Entity\Category;
use App\Pagination\PageRequest;
use App\Pagination\PageResult;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends PaginatedRepository<Category>
 */
final class CategoryRepository extends PaginatedRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /**
     * @return PageResult<Category>
     */
    public function paginateLatestForAdmin(PageRequest $pagination): PageResult
    {
        $query = $this->createQueryBuilder('category')
            ->orderBy('category.updatedAt', 'DESC')
            ->addOrderBy('category.id', 'DESC')
            ->getQuery()
        ;

        return $this->paginate($query, $pagination);
    }
}
