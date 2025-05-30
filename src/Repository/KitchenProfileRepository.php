<?php

namespace App\Repository;

use App\Entity\KitchenProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<KitchenProfile>
 */
class KitchenProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, KitchenProfile::class);
    }

    /**
     * Find kitchen profile by user ID
     */
    public function findByUserId(int $userId): ?KitchenProfile
    {
        return $this->createQueryBuilder('kp')
            ->join('kp.user', 'u')
            ->andWhere('u.id = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find kitchen profiles by position
     */
    public function findByPosition(string $position): array
    {
        return $this->createQueryBuilder('kp')
            ->andWhere('kp.posteCuisine = :position')
            ->setParameter('position', $position)
            ->orderBy('kp.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
} 