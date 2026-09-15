<?php

namespace App\Repository;

use App\Entity\Report;
use App\Pagination\PageRequest;
use App\Pagination\PageResult;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends PaginatedRepository<Report>
 */
final class ReportRepository extends PaginatedRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Report::class);
    }

    /**
     * @return PageResult<Report>
     */
    public function paginateLatestForAdmin(PageRequest $pagination): PageResult
    {
        $query = $this->createQueryBuilder('report')
            ->leftJoin('report.reporter', 'reporter')
            ->addSelect('reporter')
            ->leftJoin('report.reviewedBy', 'reviewedBy')
            ->addSelect('reviewedBy')
            ->orderBy('report.createdAt', 'DESC')
            ->addOrderBy('report.id', 'DESC')
            ->getQuery()
        ;

        return $this->paginate($query, $pagination);
    }
}
