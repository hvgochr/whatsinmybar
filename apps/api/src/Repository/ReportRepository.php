<?php

namespace App\Repository;

use App\Entity\Report;
use App\Pagination\AdminPage;
use App\Pagination\AdminPagination;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Report>
 */
final class ReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Report::class);
    }

    /**
     * @return AdminPage<Report>
     */
    public function paginateLatestForAdmin(AdminPagination $pagination): AdminPage
    {
        $query = $this->createQueryBuilder('report')
            ->orderBy('report.createdAt', 'DESC')
            ->addOrderBy('report.id', 'DESC')
            ->setFirstResult($pagination->offset())
            ->setMaxResults($pagination->pageSize)
            ->getQuery()
        ;
        $paginator = new Paginator($query, fetchJoinCollection: false);

        return new AdminPage(array_values(iterator_to_array($paginator)), count($paginator));
    }
}
