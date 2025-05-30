<?php

namespace App\Repository;

use App\Entity\Notification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * Find notifications by user
     */
    public function findByUser(int $userId): array
    {
        return $this->createQueryBuilder('n')
            ->join('n.user', 'u')
            ->andWhere('u.id = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('n.dateEnvoi', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find unread notifications by user
     */
    public function findUnreadByUser(int $userId): array
    {
        return $this->createQueryBuilder('n')
            ->join('n.user', 'u')
            ->andWhere('u.id = :userId')
            ->andWhere('n.lu = :lu')
            ->setParameter('userId', $userId)
            ->setParameter('lu', false)
            ->orderBy('n.dateEnvoi', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count unread notifications for a user
     */
    public function countUnreadByUser(int $userId): int
    {
        return $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->join('n.user', 'u')
            ->andWhere('u.id = :userId')
            ->andWhere('n.lu = :lu')
            ->setParameter('userId', $userId)
            ->setParameter('lu', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find notifications by type
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.type = :type')
            ->setParameter('type', $type)
            ->orderBy('n.dateEnvoi', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsReadForUser(int $userId): int
    {
        return $this->createQueryBuilder('n')
            ->update()
            ->set('n.lu', ':lu')
            ->set('n.dateLecture', ':dateLecture')
            ->join('n.user', 'u')
            ->andWhere('u.id = :userId')
            ->andWhere('n.lu = :currentLu')
            ->setParameter('lu', true)
            ->setParameter('dateLecture', new \DateTime())
            ->setParameter('userId', $userId)
            ->setParameter('currentLu', false)
            ->getQuery()
            ->execute();
    }
} 