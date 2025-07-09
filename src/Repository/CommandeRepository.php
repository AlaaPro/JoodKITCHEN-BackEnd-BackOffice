<?php

namespace App\Repository;

use App\Entity\Commande;
use App\Enum\OrderStatus;
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
     * Find orders by OrderStatus enum
     */
    public function findByOrderStatus(OrderStatus $status): array
    {
        return $this->findByStatus($status->value);
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
     * Get comprehensive order statistics in a single optimized query
     * @param bool $includeAverages Include average calculations (slightly more expensive)
     * @param bool $todayOnly If true, only show today's orders; if false, show all orders with today's revenue
     */
    public function getOrderStats(bool $includeAverages = false, bool $todayOnly = false): array
    {
        $today = new \DateTime('today');
        
        $qb = $this->createQueryBuilder('c')
            ->select('
                COUNT(c.id) as total_count,
                SUM(CASE WHEN c.statut = :pending THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN c.statut = :preparing THEN 1 ELSE 0 END) as preparing_count,
                SUM(CASE WHEN c.statut = :ready THEN 1 ELSE 0 END) as ready_count,
                SUM(CASE WHEN c.statut = :completed THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN c.statut = :delivering THEN 1 ELSE 0 END) as delivering_count,
                SUM(CASE WHEN c.statut = :cancelled THEN 1 ELSE 0 END) as cancelled_count,
                SUM(CASE 
                    WHEN SUBSTRING(c.dateCommande, 1, 10) = :today THEN 1 
                    ELSE 0
                END) as today_count,
                SUM(CASE 
                    WHEN SUBSTRING(c.dateCommande, 1, 10) = :today 
                    AND c.statut != :cancelled
                    THEN c.total 
                    ELSE 0 
                END) as today_revenue,
                SUM(CASE 
                    WHEN c.statut IN (:kitchen_statuses) THEN 1 
                    ELSE 0
                END) as kitchen_orders
            ')
            ->setParameter('pending', OrderStatus::PENDING->value)
            ->setParameter('preparing', OrderStatus::PREPARING->value)
            ->setParameter('ready', OrderStatus::READY->value)
            ->setParameter('completed', OrderStatus::DELIVERED->value)
            ->setParameter('delivering', OrderStatus::DELIVERING->value)
            ->setParameter('cancelled', OrderStatus::CANCELLED->value)
            ->setParameter('today', $today->format('Y-m-d'))
            ->setParameter('kitchen_statuses', [OrderStatus::PREPARING->value, OrderStatus::READY->value]);

        // Filter by today only if requested
        if ($todayOnly) {
            $qb->andWhere('DATE(c.dateCommande) = :today_date')
               ->setParameter('today_date', $today->format('Y-m-d'));
        }

        $result = $qb->getQuery()->getSingleResult();

        // Base stats used by all dashboards
        $stats = [
            // Admin dashboard stats (used in orders/index.html.twig)
            'pending' => (int)$result['pending_count'],
            'preparing' => (int)$result['preparing_count'],
            'ready' => (int)$result['ready_count'],
            'completed' => (int)$result['completed_count'],
            'delivering' => (int)$result['delivering_count'],
            'cancelled' => (int)$result['cancelled_count'],
            'todayRevenue' => round((float)($result['today_revenue'] ?? 0), 2),
            
            // POS and Kitchen dashboard stats
            'orders_today' => (int)$result['today_count'],
            'today_count' => (int)$result['today_count'], // Alias for backward compatibility
            'pending_orders' => (int)$result['pending_count'], // Alias for backward compatibility
            'revenue_today' => round((float)($result['today_revenue'] ?? 0), 2),
            'today_revenue' => round((float)($result['today_revenue'] ?? 0), 2), // Alias for backward compatibility
            'kitchen_orders' => (int)$result['kitchen_orders']
        ];

        // Add averages if requested (used by POS system)
        if ($includeAverages && $result['today_count'] > 0) {
            $stats['avg_order_value'] = round($stats['today_revenue'] / $stats['today_count'], 2);
        }

        return $stats;
    }

    /**
     * Get order statistics for a specific date range
     */
    public function getOrderStatsForDateRange(\DateTime $startDate, \DateTime $endDate, bool $includeAverages = true): array
    {
        // Status counts for the date range
        $qb = $this->createQueryBuilder('c')
            ->select([
                'COUNT(c.id) as total',
                'SUM(CASE WHEN c.statut = :pending THEN 1 ELSE 0 END) as pending',
                'SUM(CASE WHEN c.statut = :preparing THEN 1 ELSE 0 END) as preparing', 
                'SUM(CASE WHEN c.statut = :ready THEN 1 ELSE 0 END) as ready',
                'SUM(CASE WHEN c.statut = :completed THEN 1 ELSE 0 END) as completed',
                'SUM(CASE WHEN c.statut = :delivering THEN 1 ELSE 0 END) as delivering',
                'SUM(CASE WHEN c.statut = :cancelled THEN 1 ELSE 0 END) as cancelled',
                'SUM(c.total) as totalRevenue'
            ])
            ->where('c.dateCommande >= :startDate')
            ->andWhere('c.dateCommande <= :endDate')
            ->setParameter('pending', OrderStatus::PENDING->value)
            ->setParameter('preparing', OrderStatus::PREPARING->value)
            ->setParameter('ready', OrderStatus::READY->value)
            ->setParameter('completed', OrderStatus::DELIVERED->value)
            ->setParameter('delivering', OrderStatus::DELIVERING->value)
            ->setParameter('cancelled', OrderStatus::CANCELLED->value)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate);
            
        $result = $qb->getQuery()->getSingleResult();
        
        // Kitchen orders (orders that need preparation) for the date range
        $kitchenOrders = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.statut IN (:kitchen_statuses)')
            ->andWhere('c.dateCommande >= :startDate')
            ->andWhere('c.dateCommande <= :endDate')
            ->setParameter('kitchen_statuses', [
                OrderStatus::CONFIRMED->value,
                OrderStatus::PREPARING->value
            ])
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getSingleScalarResult();
            
        $stats = [
            'pending' => (int)$result['pending'],
            'preparing' => (int)$result['preparing'],
            'ready' => (int)$result['ready'],
            'completed' => (int)$result['completed'],
            'delivering' => (int)$result['delivering'],
            'cancelled' => (int)$result['cancelled'],
            'totalRevenue' => (float)($result['totalRevenue'] ?? 0),
            'totalOrders' => (int)$result['total'],
            'kitchen_orders' => (int)$kitchenOrders
        ];
        
        if ($includeAverages && $stats['totalOrders'] > 0) {
            $stats['average_order_value'] = $stats['totalRevenue'] / $stats['totalOrders'];
        } else {
            $stats['average_order_value'] = 0;
        }
        
        return $stats;
    }
} 