<?php

namespace App\Repository;

use App\Entity\Commande;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Commande>
 */
class CommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commande::class);
    }

    /**
     * Find orders by user
     */
    public function findByUser(int $userId): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.user', 'u')
            ->andWhere('u.id = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('c.dateCommande', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find orders by status
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.statut = :status')
            ->setParameter('status', $status)
            ->orderBy('c.dateCommande', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find orders for today
     */
    public function findTodayOrders(): array
    {
        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        $tomorrow = clone $today;
        $tomorrow->add(new \DateInterval('P1D'));

        return $this->createQueryBuilder('c')
            ->andWhere('c.dateCommande >= :today')
            ->andWhere('c.dateCommande < :tomorrow')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->orderBy('c.dateCommande', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find orders by date range
     */
    public function findOrdersByDateRange(\DateTime $startDate, \DateTime $endDate): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.dateCommande >= :startDate')
            ->andWhere('c.dateCommande <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('c.dateCommande', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get order statistics for POS
     */
    public function getOrderStats(): array
    {
        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        $tomorrow = clone $today;
        $tomorrow->add(new \DateInterval('P1D'));

        // Today's orders count
        $todayCount = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.dateCommande >= :today')
            ->andWhere('c.dateCommande < :tomorrow')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->getQuery()
            ->getSingleScalarResult();

        // Today's revenue
        $todayRevenue = $this->createQueryBuilder('c')
            ->select('SUM(c.total)')
            ->andWhere('c.dateCommande >= :today')
            ->andWhere('c.dateCommande < :tomorrow')
            ->andWhere('c.statut != :cancelled')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->setParameter('cancelled', 'annule')
            ->getQuery()
            ->getSingleScalarResult() ?: 0;

        // Orders in kitchen (preparation + ready)
        $kitchenOrders = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.statut IN (:statuses)')
            ->setParameter('statuses', ['en_preparation', 'pret'])
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'today_count' => (int)$todayCount,
            'today_revenue' => round((float)$todayRevenue, 2),
            'kitchen_orders' => (int)$kitchenOrders,
            'avg_order_value' => $todayCount > 0 ? round((float)$todayRevenue / (int)$todayCount, 2) : 0
        ];
    }
} 