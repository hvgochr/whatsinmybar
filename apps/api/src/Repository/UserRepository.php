<?php

namespace App\Repository;

use App\Entity\User;
use App\Pagination\PageRequest;
use App\Pagination\PageResult;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends PaginatedRepository<User>
 */
final class UserRepository extends PaginatedRepository implements PasswordUpgraderInterface
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
     * @return PageResult<User>
     */
    public function paginateLatestForAdmin(PageRequest $pagination): PageResult
    {
        $query = $this->createQueryBuilder('user')
            ->orderBy('user.createdAt', 'DESC')
            ->addOrderBy('user.id', 'DESC')
            ->getQuery()
        ;

        return $this->paginate($query, $pagination);
    }
}
