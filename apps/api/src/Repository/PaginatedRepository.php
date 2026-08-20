<?php

namespace App\Repository;

use App\Pagination\PageRequest;
use App\Pagination\PageResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @template T of object
 *
 * @extends ServiceEntityRepository<T>
 */
abstract class PaginatedRepository extends ServiceEntityRepository
{
    /**
     * @param Query<T> $query
     *
     * @return PageResult<T>
     */
    protected function paginate(Query $query, PageRequest $request): PageResult
    {
        $query
            ->setFirstResult($request->offset())
            ->setMaxResults($request->pageSize)
        ;
        $paginator = new Paginator($query, fetchJoinCollection: false);

        return new PageResult(array_values(iterator_to_array($paginator)), count($paginator));
    }
}
