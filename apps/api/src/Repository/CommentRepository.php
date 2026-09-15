<?php

namespace App\Repository;

use App\Entity\Comment;
use App\Entity\Recipe;
use App\Pagination\PageRequest;
use App\Pagination\PageResult;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends PaginatedRepository<Comment>
 */
final class CommentRepository extends PaginatedRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    /**
     * @return PageResult<Comment>
     */
    public function paginateForRecipe(Recipe $recipe, PageRequest $pagination): PageResult
    {
        $query = $this->createQueryBuilder('comment')
            ->leftJoin('comment.author', 'author')
            ->addSelect('author')
            ->andWhere('comment.recipe = :recipe')
            ->setParameter('recipe', $recipe)
            ->orderBy('comment.createdAt', 'ASC')
            ->addOrderBy('comment.id', 'ASC')
            ->getQuery()
        ;

        return $this->paginate($query, $pagination);
    }

    /**
     * @param list<Comment> $comments
     *
     * @return array<int, int>
     */
    public function replyCounts(array $comments): array
    {
        if ([] === $comments) {
            return [];
        }

        $rows = $this->createQueryBuilder('reply')
            ->select('IDENTITY(reply.parent) AS parentId', 'COUNT(reply.id) AS replyCount')
            ->andWhere('reply.parent IN (:comments)')
            ->setParameter('comments', $comments)
            ->groupBy('reply.parent')
            ->getQuery()
            ->getScalarResult()
        ;

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['parentId']] = (int) $row['replyCount'];
        }

        return $counts;
    }

    /**
     * @param list<int> $ids
     *
     * @return list<Comment>
     */
    public function findForReportContextByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        return $this->createQueryBuilder('comment')
            ->leftJoin('comment.author', 'author')
            ->addSelect('author')
            ->leftJoin('comment.recipe', 'recipe')
            ->addSelect('recipe')
            ->andWhere('comment.id IN (:ids)')
            ->setParameter('ids', array_values(array_unique($ids)))
            ->getQuery()
            ->getResult()
        ;
    }
}
