<?php

namespace App\Repository;

use App\Entity\ClientProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClientProfile>
 */
class ClientProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientProfile::class);
    }

    /**
     * Find client profile by user ID
     */
    public function findByUserId(int $userId): ?ClientProfile
    {
        return $this->createQueryBuilder('cp')
            ->join('cp.user', 'u')
            ->andWhere('u.id = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find clients with most fidelity points
     */
    public function findTopClientsByPoints(int $limit = 10): array
    {
        return $this->createQueryBuilder('cp')
            ->orderBy('cp.pointsFidelite', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
} 