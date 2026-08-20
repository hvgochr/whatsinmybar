<?php

namespace App\Repository;

use App\Entity\Ingredient;
use App\Pagination\AdminPage;
use App\Pagination\AdminPagination;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ingredient>
 */
final class IngredientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ingredient::class);
    }

    /**
     * @return AdminPage<Ingredient>
     */
    public function paginateLatestForAdmin(AdminPagination $pagination): AdminPage
    {
        $query = $this->createQueryBuilder('ingredient')
            ->orderBy('ingredient.updatedAt', 'DESC')
            ->addOrderBy('ingredient.id', 'DESC')
            ->setFirstResult($pagination->offset())
            ->setMaxResults($pagination->pageSize)
            ->getQuery()
        ;
        $paginator = new Paginator($query, fetchJoinCollection: false);

        return new AdminPage(array_values(iterator_to_array($paginator)), count($paginator));
    }
}
