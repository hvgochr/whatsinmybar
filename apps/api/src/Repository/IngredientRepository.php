<?php

namespace App\Repository;

use App\Entity\Ingredient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
     * @return list<Ingredient>
     */
    public function findLatestForAdmin(): array
    {
        return $this->createQueryBuilder('ingredient')
            ->orderBy('ingredient.updatedAt', 'DESC')
            ->addOrderBy('ingredient.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }
}
