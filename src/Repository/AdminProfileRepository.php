<?php

namespace App\Repository;

use App\Entity\AdminProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AdminProfile>
 */
class AdminProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminProfile::class);
    }

    /**
     * Find admin profile by user ID
     */
    public function findByUserId(int $userId): ?AdminProfile
    {
        return $this->createQueryBuilder('ap')
            ->join('ap.user', 'u')
            ->andWhere('u.id = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find admin profiles by internal role
     */
    public function findByInternalRole(string $role): array
    {
        return $this->createQueryBuilder('ap')
            ->andWhere('JSON_CONTAINS(ap.rolesInternes, :role) = 1')
            ->setParameter('role', json_encode($role))
            ->orderBy('ap.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
} 