<?php

namespace App\Repository;

use App\Entity\User;
use App\Pagination\AdminPage;
use App\Pagination\AdminPagination;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
final class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);

        $this->getEntityManager()->flush();
    }

    public function findOnePublicByUsername(string $username): ?User
    {
        return $this->createQueryBuilder('user')
            ->andWhere('user.username = :username')
            ->andWhere('user.deletedAt IS NULL')
            ->setParameter('username', $username)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * @return AdminPage<User>
     */
    public function paginateLatestForAdmin(AdminPagination $pagination): AdminPage
    {
        $query = $this->createQueryBuilder('user')
            ->orderBy('user.createdAt', 'DESC')
            ->addOrderBy('user.id', 'DESC')
            ->setFirstResult($pagination->offset())
            ->setMaxResults($pagination->pageSize)
            ->getQuery()
        ;
        $paginator = new Paginator($query, fetchJoinCollection: false);

        return new AdminPage(array_values(iterator_to_array($paginator)), count($paginator));
    }
}
