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

    /**
     * Find subscriptions with filters, pagination and search
     */
    public function findWithFilters(array $filters = [], int $limit = 20, int $offset = 0, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')
            ->addSelect('u');

        // Apply filters
        if (isset($filters['statut'])) {
            $qb->andWhere('a.statut = :statut')
               ->setParameter('statut', $filters['statut']);
        }

        if (isset($filters['type'])) {
            $qb->andWhere('a.type = :type')
               ->setParameter('type', $filters['type']);
        }

        if (isset($filters['search'])) {
            $qb->andWhere('(u.nom LIKE :search OR u.prenom LIKE :search OR u.email LIKE :search)')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        // Date range filtering
        if ($dateFrom) {
            $qb->andWhere('a.dateDebut >= :dateFrom')
               ->setParameter('dateFrom', new \DateTime($dateFrom));
        }

        if ($dateTo) {
            $qb->andWhere('a.dateDebut <= :dateTo')
               ->setParameter('dateTo', new \DateTime($dateTo));
        }

        return $qb->orderBy('a.dateDebut', 'DESC')
                  ->setMaxResults($limit)
                  ->setFirstResult($offset)
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Count subscriptions with filters
     */
    public function countWithFilters(array $filters = [], ?string $dateFrom = null, ?string $dateTo = null): int
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->leftJoin('a.user', 'u');

        // Apply same filters as findWithFilters
        if (isset($filters['statut'])) {
            $qb->andWhere('a.statut = :statut')
               ->setParameter('statut', $filters['statut']);
        }

        if (isset($filters['type'])) {
            $qb->andWhere('a.type = :type')
               ->setParameter('type', $filters['type']);
        }

        if (isset($filters['search'])) {
            $qb->andWhere('(u.nom LIKE :search OR u.prenom LIKE :search OR u.email LIKE :search)')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        // Date range filtering
        if ($dateFrom) {
            $qb->andWhere('a.dateDebut >= :dateFrom')
               ->setParameter('dateFrom', new \DateTime($dateFrom));
        }

        if ($dateTo) {
            $qb->andWhere('a.dateDebut <= :dateTo')
               ->setParameter('dateTo', new \DateTime($dateTo));
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
} 