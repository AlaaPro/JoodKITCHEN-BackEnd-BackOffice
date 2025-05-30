<?php

namespace App\Repository;

use App\Entity\Abonnement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Abonnement>
 */
class AbonnementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Abonnement::class);
    }

    /**
     * Find active subscriptions
     */
    public function findActiveSubscriptions(): array
    {
        $now = new \DateTime();
        
        return $this->createQueryBuilder('a')
            ->andWhere('a.statut = :status')
            ->andWhere('a.dateDebut <= :now')
            ->andWhere('a.dateFin >= :now')
            ->setParameter('status', 'actif')
            ->setParameter('now', $now)
            ->orderBy('a.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find subscriptions by user
     */
    public function findByUser(int $userId): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.user', 'u')
            ->andWhere('u.id = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('a.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find expiring subscriptions
     */
    public function findExpiringSubscriptions(int $days = 7): array
    {
        $now = new \DateTime();
        $futureDate = clone $now;
        $futureDate->add(new \DateInterval('P'.$days.'D'));

        return $this->createQueryBuilder('a')
            ->andWhere('a.statut = :status')
            ->andWhere('a.dateFin BETWEEN :now AND :futureDate')
            ->setParameter('status', 'actif')
            ->setParameter('now', $now)
            ->setParameter('futureDate', $futureDate)
            ->orderBy('a.dateFin', 'ASC')
            ->getQuery()
            ->getResult();
    }
} 