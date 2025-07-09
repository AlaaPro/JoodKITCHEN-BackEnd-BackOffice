<?php

namespace App\Service;

use App\Enum\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class AnalyticsService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CacheInterface $cache
    ) {}

    /**
     * Get daily sales report
     */
    public function getDailySalesReport(?\DateTime $date = null): array
    {
        $date = $date ?? new \DateTime();
        $cacheKey = 'analytics.daily.' . $date->format('Y-m-d');
        
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($date) {
            $item->expiresAfter(3600); // 1 hour cache for daily reports
            
            $qb = $this->entityManager->createQueryBuilder();
            
            // Order statistics
            $orderStats = $qb->select('COUNT(c.id) as total_orders, SUM(c.total) as total_revenue')
                ->from('App\Entity\Commande', 'c')
                ->where('DATE(c.dateCommande) = :date')
                ->andWhere('c.statut != :cancelled')
                ->setParameter('date', $date->format('Y-m-d'))
                ->setParameter('cancelled', OrderStatus::CANCELLED->value)
                ->getQuery()
                ->getOneOrNullResult();

            // Average order value
            $avgOrderValue = $orderStats['total_orders'] > 0 
                ? $orderStats['total_revenue'] / $orderStats['total_orders'] 
                : 0;

            // Most popular plats
            $qb = $this->entityManager->createQueryBuilder();
            $popularPlats = $qb->select('p.nom, COUNT(ca.id) as quantity_sold, SUM(ca.prixUnitaire * ca.quantite) as revenue')
                ->from('App\Entity\CommandeArticle', 'ca')
                ->join('ca.plat', 'p')
                ->join('ca.commande', 'c')
                ->where('DATE(c.dateCommande) = :date')
                ->andWhere('c.statut != :cancelled')
                ->setParameter('date', $date->format('Y-m-d'))
                ->setParameter('cancelled', OrderStatus::CANCELLED->value)
                ->groupBy('p.id')
                ->orderBy('quantity_sold', 'DESC')
                ->setMaxResults(10)
                ->getQuery()
                ->getArrayResult();

            // Orders by hour
            $qb = $this->entityManager->createQueryBuilder();
            $ordersByHour = $qb->select('HOUR(c.dateCommande) as hour, COUNT(c.id) as orders_count')
                ->from('App\Entity\Commande', 'c')
                ->where('DATE(c.dateCommande) = :date')
                ->setParameter('date', $date->format('Y-m-d'))
                ->groupBy('hour')
                ->orderBy('hour', 'ASC')
                ->getQuery()
                ->getArrayResult();

            return [
                'date' => $date->format('Y-m-d'),
                'summary' => [
                    'total_orders' => (int)$orderStats['total_orders'],
                    'total_revenue' => (float)$orderStats['total_revenue'],
                    'avg_order_value' => round($avgOrderValue, 2),
                ],
                'popular_plats' => $popularPlats,
                'orders_by_hour' => $ordersByHour,
                'generated_at' => (new \DateTime())->format('Y-m-d H:i:s')
            ];
        });
    }

    /**
     * Get weekly performance report
     */
    public function getWeeklyReport(?\DateTime $startDate = null): array
    {
        $startDate = $startDate ?? new \DateTime('monday this week');
        $endDate = (clone $startDate)->modify('+6 days');
        
        $cacheKey = 'analytics.weekly.' . $startDate->format('Y-m-d');
        
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($startDate, $endDate) {
            $item->expiresAfter(7200); // 2 hours cache
            
            $qb = $this->entityManager->createQueryBuilder();
            
            // Daily breakdown
            $dailyStats = $qb->select('DATE(c.dateCommande) as date, COUNT(c.id) as orders, SUM(c.total) as revenue')
                ->from('App\Entity\Commande', 'c')
                ->where('c.dateCommande BETWEEN :start AND :end')
                ->andWhere('c.statut != :cancelled')
                ->setParameter('start', $startDate)
                ->setParameter('end', $endDate->modify('+1 day'))
                ->setParameter('cancelled', OrderStatus::CANCELLED->value)
                ->groupBy('date')
                ->orderBy('date', 'ASC')
                ->getQuery()
                ->getArrayResult();

            // Customer statistics
            $qb = $this->entityManager->createQueryBuilder();
            $customerStats = $qb->select('COUNT(DISTINCT c.user) as unique_customers, COUNT(c.id) as total_orders')
                ->from('App\Entity\Commande', 'c')
                ->where('c.dateCommande BETWEEN :start AND :end')
                ->andWhere('c.statut != :cancelled')
                ->setParameter('start', $startDate)
                ->setParameter('end', $endDate->modify('+1 day'))
                ->setParameter('cancelled', OrderStatus::CANCELLED->value)
                ->getQuery()
                ->getOneOrNullResult();

            // Menu performance
            $qb = $this->entityManager->createQueryBuilder();
            $menuPerformance = $qb->select('m.nom, COUNT(ca.id) as orders_count, SUM(ca.prixUnitaire * ca.quantite) as revenue')
                ->from('App\Entity\CommandeArticle', 'ca')
                ->join('ca.menu', 'm')
                ->join('ca.commande', 'c')
                ->where('c.dateCommande BETWEEN :start AND :end')
                ->andWhere('c.statut != :cancelled')
                ->setParameter('start', $startDate)
                ->setParameter('end', $endDate->modify('+1 day'))
                ->setParameter('cancelled', OrderStatus::CANCELLED->value)
                ->groupBy('m.id')
                ->orderBy('revenue', 'DESC')
                ->getQuery()
                ->getArrayResult();

            return [
                'period' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d')
                ],
                'daily_breakdown' => $dailyStats,
                'customer_stats' => $customerStats,
                'menu_performance' => $menuPerformance,
                'generated_at' => (new \DateTime())->format('Y-m-d H:i:s')
            ];
        });
    }

    /**
     * Get customer analytics
     */
    public function getCustomerAnalytics(): array
    {
        return $this->cache->get('analytics.customers', function (ItemInterface $item) {
            $item->expiresAfter(3600); // 1 hour cache
            
            $qb = $this->entityManager->createQueryBuilder();
            
            // Top customers by orders
            $topCustomers = $qb->select('u.nom, u.prenom, COUNT(c.id) as total_orders, SUM(c.total) as total_spent')
                ->from('App\Entity\Commande', 'c')
                ->join('c.user', 'u')
                ->where('c.dateCommande >= :lastMonth')
                ->andWhere('c.statut != :cancelled')
                ->setParameter('lastMonth', new \DateTime('-1 month'))
                ->setParameter('cancelled', OrderStatus::CANCELLED->value)
                ->groupBy('u.id')
                ->orderBy('total_spent', 'DESC')
                ->setMaxResults(10)
                ->getQuery()
                ->getArrayResult();

            // Customer retention
            $qb = $this->entityManager->createQueryBuilder();
            $newCustomers = $qb->select('COUNT(DISTINCT u.id)')
                ->from('App\Entity\User', 'u')
                ->join('u.commandes', 'c')
                ->where('u.createdAt >= :lastMonth')
                ->setParameter('lastMonth', new \DateTime('-1 month'))
                ->getQuery()
                ->getSingleScalarResult();

            $qb = $this->entityManager->createQueryBuilder();
            $returningCustomers = $qb->select('COUNT(DISTINCT u.id)')
                ->from('App\Entity\User', 'u')
                ->join('u.commandes', 'c')
                ->where('u.createdAt < :lastMonth')
                ->andWhere('c.dateCommande >= :lastMonth')
                ->setParameter('lastMonth', new \DateTime('-1 month'))
                ->getQuery()
                ->getSingleScalarResult();

            return [
                'top_customers' => $topCustomers,
                'retention' => [
                    'new_customers' => (int)$newCustomers,
                    'returning_customers' => (int)$returningCustomers,
                    'retention_rate' => $newCustomers > 0 ? round(($returningCustomers / $newCustomers) * 100, 2) : 0
                ],
                'generated_at' => (new \DateTime())->format('Y-m-d H:i:s')
            ];
        });
    }

    /**
     * Get inventory insights
     */
    public function getInventoryInsights(): array
    {
        return $this->cache->get('analytics.inventory', function (ItemInterface $item) {
            $item->expiresAfter(1800); // 30 minutes cache
            
            $qb = $this->entityManager->createQueryBuilder();
            
            // Dish performance
            $dishPerformance = $qb->select('p.nom, p.categorie, p.prix, COUNT(ca.id) as times_ordered, AVG(ca.quantite) as avg_quantity')
                ->from('App\Entity\Plat', 'p')
                ->leftJoin('p.commandeArticles', 'ca')
                ->leftJoin('ca.commande', 'c')
                ->where('c.dateCommande >= :lastWeek OR c.dateCommande IS NULL')
                ->andWhere('p.disponible = true')
                ->setParameter('lastWeek', new \DateTime('-1 week'))
                ->groupBy('p.id')
                ->orderBy('times_ordered', 'DESC')
                ->getQuery()
                ->getArrayResult();

            // Low performers (plats not ordered recently)
            $qb = $this->entityManager->createQueryBuilder();
            $lowPerformers = $qb->select('p.nom, p.categorie, p.prix')
                ->from('App\Entity\Plat', 'p')
                ->leftJoin('p.commandeArticles', 'ca')
                ->leftJoin('ca.commande', 'c', 'WITH', 'c.dateCommande >= :lastWeek')
                ->where('p.disponible = true')
                ->andWhere('c.id IS NULL')
                ->setParameter('lastWeek', new \DateTime('-1 week'))
                ->getQuery()
                ->getArrayResult();

            return [
                'dish_performance' => $dishPerformance,
                'low_performers' => $lowPerformers,
                'recommendations' => $this->generateInventoryRecommendations($dishPerformance, $lowPerformers),
                'generated_at' => (new \DateTime())->format('Y-m-d H:i:s')
            ];
        });
    }

    /**
     * Get financial summary
     */
    public function getFinancialSummary(?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        $startDate = $startDate ?? new \DateTime('first day of this month');
        $endDate = $endDate ?? new \DateTime();
        
        $cacheKey = 'analytics.financial.' . $startDate->format('Y-m-d') . '_' . $endDate->format('Y-m-d');
        
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($startDate, $endDate) {
            $item->expiresAfter(1800); // 30 minutes cache
            
            $qb = $this->entityManager->createQueryBuilder();
            
            // Revenue breakdown
            $revenue = $qb->select('SUM(c.total) as total_revenue, COUNT(c.id) as total_orders')
                ->from('App\Entity\Commande', 'c')
                ->where('c.dateCommande BETWEEN :start AND :end')
                ->andWhere('c.statut != :cancelled')
                ->setParameter('start', $startDate)
                ->setParameter('end', $endDate)
                ->setParameter('cancelled', OrderStatus::CANCELLED->value)
                ->getQuery()
                ->getOneOrNullResult();

            // Payment methods breakdown
            $qb = $this->entityManager->createQueryBuilder();
            $paymentMethods = $qb->select('p.methodePaiement, COUNT(p.id) as count, SUM(p.montant) as total')
                ->from('App\Entity\Payment', 'p')
                ->join('p.commande', 'c')
                ->where('c.dateCommande BETWEEN :start AND :end')
                ->andWhere('p.statut = :validated')
                ->setParameter('start', $startDate)
                ->setParameter('end', $endDate)
                ->setParameter('validated', 'valide')
                ->groupBy('p.methodePaiement')
                ->getQuery()
                ->getArrayResult();

            // Revenue by category
            $qb = $this->entityManager->createQueryBuilder();
            $categoryRevenue = $qb->select('p.categorie, SUM(ca.prixUnitaire * ca.quantite) as revenue')
                ->from('App\Entity\CommandeArticle', 'ca')
                ->join('ca.plat', 'p')
                ->join('ca.commande', 'c')
                ->where('c.dateCommande BETWEEN :start AND :end')
                ->andWhere('c.statut != :cancelled')
                ->setParameter('start', $startDate)
                ->setParameter('end', $endDate)
                ->setParameter('cancelled', OrderStatus::CANCELLED->value)
                ->groupBy('p.categorie')
                ->orderBy('revenue', 'DESC')
                ->getQuery()
                ->getArrayResult();

            return [
                'period' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d')
                ],
                'summary' => [
                    'total_revenue' => (float)$revenue['total_revenue'],
                    'total_orders' => (int)$revenue['total_orders'],
                    'avg_order_value' => $revenue['total_orders'] > 0 
                        ? round($revenue['total_revenue'] / $revenue['total_orders'], 2) 
                        : 0
                ],
                'payment_methods' => $paymentMethods,
                'category_revenue' => $categoryRevenue,
                'generated_at' => (new \DateTime())->format('Y-m-d H:i:s')
            ];
        });
    }

    /**
     * Get operational metrics
     */
    public function getOperationalMetrics(): array
    {
        return $this->cache->get('analytics.operational', function (ItemInterface $item) {
            $item->expiresAfter(1800); // 30 minutes cache
            
            $qb = $this->entityManager->createQueryBuilder();
            
            // Order distribution by status
            $statusDistribution = $qb->select('c.statut, COUNT(c.id) as count')
                ->from('App\Entity\Commande', 'c')
                ->where('c.dateCommande >= :lastWeek')
                ->setParameter('lastWeek', new \DateTime('-1 week'))
                ->groupBy('c.statut')
                ->getQuery()
                ->getArrayResult();
            
            // Kitchen efficiency - orders in preparation vs ready
            $qb = $this->entityManager->createQueryBuilder();
            $kitchenMetrics = $qb->select('COUNT(c.id) as kitchen_orders')
                ->from('App\Entity\Commande', 'c')
                ->where('c.statut IN (:statuses)')
                ->setParameter('statuses', [OrderStatus::PREPARING->value, OrderStatus::READY->value])
                ->getQuery()
                ->getSingleScalarResult();
            
            // Calculate completion rate
            $qb = $this->entityManager->createQueryBuilder();
            $completedOrders = $qb->select('COUNT(c.id)')
                ->from('App\Entity\Commande', 'c')
                ->where('c.dateCommande >= :lastWeek')
                ->andWhere('c.statut = :completed')
                ->setParameter('lastWeek', new \DateTime('-1 week'))
                ->setParameter('completed', OrderStatus::DELIVERED->value)
                ->getQuery()
                ->getSingleScalarResult();
            
            $totalOrders = array_sum(array_map(fn($s) => $s['count'], $statusDistribution));
            $completionRate = $totalOrders > 0 ? round(($completedOrders / $totalOrders) * 100, 2) : 0;
            
            // Revenue trends
            $qb = $this->entityManager->createQueryBuilder();
            $revenueMetrics = $qb->select('
                    SUM(CASE WHEN DATE(c.dateCommande) = CURRENT_DATE() THEN c.total ELSE 0 END) as today_revenue,
                    SUM(CASE WHEN DATE(c.dateCommande) = DATE(DATE_SUB(NOW(), INTERVAL 1 DAY)) THEN c.total ELSE 0 END) as yesterday_revenue
                ')
                ->from('App\Entity\Commande', 'c')
                ->where('c.dateCommande >= :twoDaysAgo')
                ->andWhere('c.statut != :cancelled')
                ->setParameter('twoDaysAgo', new \DateTime('-2 days'))
                ->setParameter('cancelled', OrderStatus::CANCELLED->value)
                ->getQuery()
                ->getOneOrNullResult();
            
            $todayRevenue = (float)($revenueMetrics['today_revenue'] ?? 0);
            $yesterdayRevenue = (float)($revenueMetrics['yesterday_revenue'] ?? 0);
            $revenueGrowth = $yesterdayRevenue > 0 ? round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100, 2) : 0;
            
            return [
                'status_distribution' => $statusDistribution,
                'kitchen_orders' => (int)$kitchenMetrics,
                'completion_rate' => $completionRate,
                'revenue_metrics' => [
                    'today' => $todayRevenue,
                    'yesterday' => $yesterdayRevenue,
                    'growth_percentage' => $revenueGrowth
                ],
                'efficiency_score' => $this->calculateEfficiencyScore([], $statusDistribution),
                'generated_at' => (new \DateTime())->format('Y-m-d H:i:s')
            ];
        });
    }

    /**
     * Clear analytics cache
     */
    public function clearAnalyticsCache(): void
    {
        // Pattern-based cache clearing (if supported by cache adapter)
        $patterns = [
            'analytics.daily_sales.',
            'analytics.weekly.',
            'analytics.customers',
            'analytics.inventory',
            'analytics.financial.',
            'analytics.operational'
        ];

        foreach ($patterns as $pattern) {
            try {
                $this->cache->delete($pattern);
            } catch (\Exception $e) {
                // Continue clearing other caches
            }
        }
    }

    private function generateInventoryRecommendations(array $performers, array $lowPerformers): array
    {
        $recommendations = [];

        // Recommend promoting low performers
        if (count($lowPerformers) > 0) {
            $recommendations[] = [
                'type' => 'promotion',
                'message' => 'Consider promoting ' . count($lowPerformers) . ' underperforming plats',
                'action' => 'Create special offers for low-selling items'
            ];
        }

        // Recommend restocking popular items
        $highPerformers = array_filter($performers, fn($p) => $p['times_ordered'] > 10);
        if (count($highPerformers) > 0) {
            $recommendations[] = [
                'type' => 'restock',
                'message' => 'Ensure adequate inventory for ' . count($highPerformers) . ' popular plats',
                'action' => 'Monitor stock levels for high-demand items'
            ];
        }

        return $recommendations;
    }

    private function calculateEfficiencyScore(array $preparationTimes, array $statusDistribution): int
    {
        // Simple efficiency calculation based on completion rate and order flow
        $completedOrders = array_sum(array_map(fn($s) => $s['statut'] === OrderStatus::DELIVERED->value ? $s['count'] : 0, $statusDistribution));
        $totalOrders = array_sum(array_map(fn($s) => $s['count'], $statusDistribution));
        
        if ($totalOrders === 0) {
            return 0;
        }
        
        $completionRate = ($completedOrders / $totalOrders) * 100;
        
        // Additional factors could include preparation time, customer satisfaction, etc.
        return min(100, max(0, (int)$completionRate));
    }
} 