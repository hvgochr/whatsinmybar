<?php

namespace App\Repository;

use App\Entity\Category;
use App\Pagination\AdminPage;
use App\Pagination\AdminPagination;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
final class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /**
     * @return AdminPage<Category>
     */
    public function paginateLatestForAdmin(AdminPagination $pagination): AdminPage
    {
        $query = $this->createQueryBuilder('category')
            ->orderBy('category.updatedAt', 'DESC')
            ->addOrderBy('category.id', 'DESC')
            ->setFirstResult($pagination->offset())
            ->setMaxResults($pagination->pageSize)
            ->getQuery()
        ;
        $paginator = new Paginator($query, fetchJoinCollection: false);

        return new AdminPage(array_values(iterator_to_array($paginator)), count($paginator));
    }
}
