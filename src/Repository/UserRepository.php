<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * Find active users
     */
    public function findActiveUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.isActive = :isActive')
            ->setParameter('isActive', true)
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find users by role
     */
    public function findByRole(string $role): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%"'.$role.'"%')
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search customers for POS
     */
    public function searchCustomers(string $query, int $limit = 10): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.roles LIKE :role')
            ->andWhere('u.isActive = :isActive')
            ->andWhere('
                u.nom LIKE :query OR 
                u.prenom LIKE :query OR 
                u.email LIKE :query OR 
                u.telephone LIKE :query OR
                CONCAT(u.prenom, \' \', u.nom) LIKE :query OR
                CONCAT(u.nom, \' \', u.prenom) LIKE :query
            ')
            ->setParameter('role', '%"ROLE_CLIENT"%')
            ->setParameter('isActive', true)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('u.nom', 'ASC')
            ->addOrderBy('u.prenom', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get customer statistics
     */
    public function getCustomerStats(): array
    {
        // Total customers
        $totalCustomers = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.roles LIKE :role')
            ->andWhere('u.isActive = :isActive')
            ->setParameter('role', '%"ROLE_CLIENT"%')
            ->setParameter('isActive', true)
            ->getQuery()
            ->getSingleScalarResult();

        // New customers this month
        $thisMonth = new \DateTime('first day of this month');
        $newCustomers = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.roles LIKE :role')
            ->andWhere('u.isActive = :isActive')
            ->andWhere('u.createdAt >= :thisMonth')
            ->setParameter('role', '%"ROLE_CLIENT"%')
            ->setParameter('isActive', true)
            ->setParameter('thisMonth', $thisMonth)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => (int)$totalCustomers,
            'new_this_month' => (int)$newCustomers
        ];
    }
} 