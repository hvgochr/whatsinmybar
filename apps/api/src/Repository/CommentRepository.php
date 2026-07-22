<?php

namespace App\Repository;

use App\Entity\Comment;
use App\Entity\Recipe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Comment>
 */
final class CommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    /**
     * @return list<Comment>
     */
    public function findForRecipe(Recipe $recipe): array
    {
        return $this->createQueryBuilder('comment')
            ->andWhere('comment.recipe = :recipe')
            ->setParameter('recipe', $recipe)
            ->orderBy('comment.createdAt', 'ASC')
            ->addOrderBy('comment.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
