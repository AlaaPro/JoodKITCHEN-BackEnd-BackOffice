<?php

namespace App\Service;

use App\Entity\Abonnement;
use App\Entity\AbonnementSelection;
use App\Repository\AbonnementRepository;
use App\Repository\AbonnementSelectionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Psr\Log\LoggerInterface;

/**
 * AbonnementStatisticsService - Comprehensive subscription analytics and business intelligence
 * 
 * This service provides advanced subscription analytics including:
 * - Dashboard statistics and KPIs
 * - Revenue analytics and forecasting
 * - Cuisine preference trends
 * - Conversion rate tracking
 * - Customer behavior analytics
 * - Kitchen planning insights
 * - Business intelligence reporting
 * 
 * Performance optimized with multi-layer caching strategy
 */
class AbonnementStatisticsService
{
    private const CACHE_TTL_STATS = 1800; // 30 minutes for statistics
    private const CACHE_TTL_REALTIME = 300; // 5 minutes for real-time data
    private const CACHE_TTL_REPORTS = 3600; // 1 hour for reports
    private const CACHE_PREFIX = 'abonnement_stats_';

    public function __construct(
        private AbonnementRepository $abonnementRepository,
        private AbonnementSelectionRepository $selectionRepository,
        private EntityManagerInterface $entityManager,
        private CacheInterface $cache,
        private LoggerInterface $logger
    ) {}

    /**
     * Get comprehensive dashboard statistics
     */
    public function getDashboardStatistics(): array
    {
        $cacheKey = self::CACHE_PREFIX . 'dashboard';
        
        return $this->cache->get($cacheKey, function (ItemInterface $item) {
            $item->expiresAfter(self::CACHE_TTL_STATS);
            
            try {
                $stats = [
                    'overview' => $this->getSubscriptionOverview(),
                    'revenue' => $this->getRevenueMetrics(),
                    'conversion' => $this->getConversionMetrics(),
                    'cuisine_trends' => $this->getCuisineTrends(),
                    'alerts' => $this->getSystemAlerts(),
                    'recent_activity' => $this->getRecentActivity(),
                    'generated_at' => (new \DateTime())->format('Y-m-d H:i:s')
                ];

                $this->logger->info('Dashboard statistics generated', [
                    'total_subscriptions' => $stats['overview']['total'],
                    'active_subscriptions' => $stats['overview']['actif'],
                    'weekly_revenue' => $stats['revenue']['weekly_total']
                ]);

                return $stats;

            } catch (\Exception $e) {
                $this->logger->error('Failed to generate dashboard statistics', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                // Return fallback data
                return $this->getFallbackStatistics();
            }
        });
    }

    /**
     * Get subscription overview by status
     */
    public function getSubscriptionOverview(): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        $statusCounts = $qb->select('a.statut, COUNT(a.id) as count')
            ->from(Abonnement::class, 'a')
            ->groupBy('a.statut')
            ->getQuery()
            ->getArrayResult();

        $overview = [
            'en_confirmation' => 0,
            'actif' => 0,
            'suspendu' => 0,
            'expire' => 0,
            'annule' => 0,
            'total' => 0
        ];

        foreach ($statusCounts as $status) {
            $overview[$status['statut']] = (int) $status['count'];
            $overview['total'] += (int) $status['count'];
        }

        // Calculate percentages
        $total = $overview['total'];
        if ($total > 0) {
            foreach ($overview as $key => $value) {
                if ($key !== 'total') {
                    $overview[$key . '_percentage'] = round(($value / $total) * 100, 1);
                }
            }
        }

        // Add trend data (compare with last week)
        $overview['trends'] = $this->getStatusTrends();

        return $overview;
    }

    /**
     * Get revenue metrics and projections
     */
    public function getRevenueMetrics(): array
    {
        // Current week revenue
        $weekStart = new \DateTime('monday this week');
        $weekEnd = new \DateTime('sunday this week');
        
        $qb = $this->entityManager->createQueryBuilder();
        $weeklyRevenue = $qb->select('SUM(s.prix) as total')
            ->from(AbonnementSelection::class, 's')
            ->join('s.abonnement', 'a')
            ->where('s.dateRepas BETWEEN :weekStart AND :weekEnd')
            ->andWhere('a.statut IN (:activeStatuses)')
            ->setParameter('weekStart', $weekStart)
            ->setParameter('weekEnd', $weekEnd)
            ->setParameter('activeStatuses', ['actif', 'suspendu'])
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // Monthly revenue
        $monthStart = new \DateTime('first day of this month');
        $monthEnd = new \DateTime('last day of this month');
        
        $qb = $this->entityManager->createQueryBuilder();
        $monthlyRevenue = $qb->select('SUM(s.prix) as total')
            ->from(AbonnementSelection::class, 's')
            ->join('s.abonnement', 'a')
            ->where('s.dateRepas BETWEEN :monthStart AND :monthEnd')
            ->andWhere('a.statut IN (:activeStatuses)')
            ->setParameter('monthStart', $monthStart)
            ->setParameter('monthEnd', $monthEnd)
            ->setParameter('activeStatuses', ['actif', 'suspendu'])
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // Average subscription value
        $qb = $this->entityManager->createQueryBuilder();
        $avgSubscriptionValue = $qb->select('AVG(s.prix) as average')
            ->from(AbonnementSelection::class, 's')
            ->join('s.abonnement', 'a')
            ->where('a.statut = :actif')
            ->setParameter('actif', 'actif')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // Revenue projections
        $projectedMonthly = $this->calculateMonthlyProjection();
        $projectedQuarterly = $projectedMonthly * 3;

        return [
            'weekly_total' => round($weeklyRevenue, 2),
            'monthly_total' => round($monthlyRevenue, 2),
            'average_subscription_value' => round($avgSubscriptionValue, 2),
            'projected_monthly' => round($projectedMonthly, 2),
            'projected_quarterly' => round($projectedQuarterly, 2),
            'currency' => 'MAD',
            'growth_rate' => $this->calculateRevenueGrowthRate()
        ];
    }

    /**
     * Get conversion metrics (en_confirmation → actif)
     */
    public function getConversionMetrics(): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        // Subscriptions created in the last 30 days
        $totalCreated = $qb->select('COUNT(a.id)')
            ->from(Abonnement::class, 'a')
            ->where('a.createdAt >= :thirtyDaysAgo')
            ->setParameter('thirtyDaysAgo', new \DateTime('-30 days'))
            ->getQuery()
            ->getSingleScalarResult();

        // Subscriptions activated in the last 30 days
        $qb = $this->entityManager->createQueryBuilder();
        $totalActivated = $qb->select('COUNT(a.id)')
            ->from(Abonnement::class, 'a')
            ->where('a.createdAt >= :thirtyDaysAgo')
            ->andWhere('a.statut = :actif')
            ->setParameter('thirtyDaysAgo', new \DateTime('-30 days'))
            ->setParameter('actif', 'actif')
            ->getQuery()
            ->getSingleScalarResult();

        // Currently pending confirmation
        $qb = $this->entityManager->createQueryBuilder();
        $pendingConfirmation = $qb->select('COUNT(a.id)')
            ->from(Abonnement::class, 'a')
            ->where('a.statut = :pending')
            ->setParameter('pending', 'en_confirmation')
            ->getQuery()
            ->getSingleScalarResult();

        $conversionRate = $totalCreated > 0 ? round(($totalActivated / $totalCreated) * 100, 1) : 0;

        return [
            'total_created_30d' => (int) $totalCreated,
            'total_activated_30d' => (int) $totalActivated,
            'pending_confirmation' => (int) $pendingConfirmation,
            'conversion_rate' => $conversionRate,
            'conversion_trend' => $this->getConversionTrend()
        ];
    }

    /**
     * Get cuisine preference trends and analytics
     */
    public function getCuisineTrends(string $period = 'week'): array
    {
        $cacheKey = self::CACHE_PREFIX . "cuisine_trends_{$period}";
        
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($period) {
            $item->expiresAfter(self::CACHE_TTL_STATS);
            
            [$startDate, $endDate] = $this->getPeriodDates($period);
            
            $qb = $this->entityManager->createQueryBuilder();
            $cuisineStats = $qb->select('s.cuisineType, COUNT(s.id) as count')
                ->from(AbonnementSelection::class, 's')
                ->where('s.dateRepas BETWEEN :start AND :end')
                ->andWhere('s.cuisineType IS NOT NULL')
                ->setParameter('start', $startDate)
                ->setParameter('end', $endDate)
                ->groupBy('s.cuisineType')
                ->orderBy('count', 'DESC')
                ->getQuery()
                ->getArrayResult();

            $total = array_sum(array_column($cuisineStats, 'count'));
            
            $trends = [
                'period' => $period,
                'total_selections' => $total,
                'cuisine_breakdown' => [],
                'most_popular' => null,
                'least_popular' => null
            ];

            foreach ($cuisineStats as $cuisine) {
                $percentage = $total > 0 ? round(($cuisine['count'] / $total) * 100, 1) : 0;
                $trends['cuisine_breakdown'][$cuisine['cuisineType']] = [
                    'count' => (int) $cuisine['count'],
                    'percentage' => $percentage,
                    'label' => $this->getCuisineLabel($cuisine['cuisineType'])
                ];
            }

            if (!empty($cuisineStats)) {
                $trends['most_popular'] = $cuisineStats[0]['cuisineType'];
                $trends['least_popular'] = end($cuisineStats)['cuisineType'];
            }

            return $trends;
        });
    }

    /**
     * Get system alerts for admin attention
     */
    public function getSystemAlerts(): array
    {
        $alerts = [];

        // High number of pending confirmations
        $pendingCount = $this->abonnementRepository->count(['statut' => 'en_confirmation']);
        if ($pendingCount > 10) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Confirmations en Attente',
                'message' => "{$pendingCount} abonnements en attente de confirmation de paiement",
                'action' => 'Vérifier les paiements CMI et relancer les clients',
                'priority' => 'high'
            ];
        }

        // Incomplete weekly selections
        $incompleteSelections = $this->getIncompleteSelectionsCount();
        if ($incompleteSelections > 5) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Sélections Incomplètes',
                'message' => "{$incompleteSelections} abonnements avec sélections incomplètes cette semaine",
                'action' => 'Envoyer des rappels aux clients',
                'priority' => 'medium'
            ];
        }

        // Low conversion rate
        $conversionRate = $this->getConversionMetrics()['conversion_rate'];
        if ($conversionRate < 70) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Taux de Conversion Faible',
                'message' => "Taux de conversion: {$conversionRate}% (recommandé: >70%)",
                'action' => 'Optimiser le processus de confirmation',
                'priority' => 'medium'
            ];
        }

        return $alerts;
    }

    /**
     * Get recent subscription activity
     */
    public function getRecentActivity(int $limit = 10): array
    {
        $cacheKey = self::CACHE_PREFIX . "recent_activity_{$limit}";
        
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($limit) {
            $item->expiresAfter(self::CACHE_TTL_REALTIME);
            
            $qb = $this->entityManager->createQueryBuilder();
            $recentSubscriptions = $qb->select('a', 'u')
                ->from(Abonnement::class, 'a')
                ->join('a.user', 'u')
                ->orderBy('a.createdAt', 'DESC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();

            return array_map(function (Abonnement $abonnement) {
                return [
                    'id' => $abonnement->getId(),
                    'user_name' => $abonnement->getUser()->getPrenom() . ' ' . $abonnement->getUser()->getNom(),
                    'user_email' => $abonnement->getUser()->getEmail(),
                    'type' => $abonnement->getTypeLabel(),
                    'statut' => $abonnement->getStatutLabel(),
                    'statut_color' => $abonnement->getStatusColor(),
                    'created_at' => $abonnement->getCreatedAt()->format('Y-m-d H:i'),
                    'is_new' => $abonnement->getCreatedAt() > new \DateTime('-24 hours')
                ];
            }, $recentSubscriptions);
        });
    }

    /**
     * Get weekly calendar data for meal planning
     */
    public function getWeeklyCalendarData(string $weekStart): array
    {
        $cacheKey = self::CACHE_PREFIX . "calendar_{$weekStart}";
        
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($weekStart) {
            $item->expiresAfter(self::CACHE_TTL_STATS);
            
            $startDate = new \DateTime($weekStart);
            $endDate = (clone $startDate)->modify('+6 days');
            
            $qb = $this->entityManager->createQueryBuilder();
            $selections = $qb->select('s.dateRepas, s.cuisineType, COUNT(s.id) as count')
                ->from(AbonnementSelection::class, 's')
                ->where('s.dateRepas BETWEEN :start AND :end')
                ->setParameter('start', $startDate)
                ->setParameter('end', $endDate)
                ->groupBy('s.dateRepas', 's.cuisineType')
                ->getQuery()
                ->getArrayResult();

            // Get incomplete selections
            $qb = $this->entityManager->createQueryBuilder();
            $incompleteSelections = $qb->select('DATE(s.dateRepas) as date, COUNT(DISTINCT a.id) as incomplete_count')
                ->from(Abonnement::class, 'a')
                ->leftJoin(AbonnementSelection::class, 's', 'WITH', 'a.id = s.abonnement AND s.dateRepas BETWEEN :start AND :end')
                ->where('a.statut = :actif')
                ->andWhere('a.dateDebut <= :end')
                ->andWhere('a.dateFin >= :start')
                ->groupBy('date')
                ->having('COUNT(s.id) = 0 OR COUNT(s.id) < :expectedMeals')
                ->setParameter('start', $startDate)
                ->setParameter('end', $endDate)
                ->setParameter('actif', 'actif')
                ->setParameter('expectedMeals', 1)
                ->getQuery()
                ->getArrayResult();

            // Organize data by day
            $calendarData = [];
            for ($i = 0; $i < 7; $i++) {
                $date = (clone $startDate)->modify("+{$i} days");
                $dateStr = $date->format('Y-m-d');
                
                $calendarData[$dateStr] = [
                    'date' => $dateStr,
                    'day_name' => $this->getDayName($date->format('w')),
                    'cuisine_counts' => [
                        'marocain' => 0,
                        'italien' => 0,
                        'international' => 0
                    ],
                    'total_selections' => 0,
                    'incomplete_count' => 0
                ];
            }

            // Fill with selection data
            foreach ($selections as $selection) {
                $dateStr = $selection['dateRepas']->format('Y-m-d');
                if (isset($calendarData[$dateStr])) {
                    $cuisineType = $selection['cuisineType'] ?? 'unknown';
                    if (isset($calendarData[$dateStr]['cuisine_counts'][$cuisineType])) {
                        $calendarData[$dateStr]['cuisine_counts'][$cuisineType] = (int) $selection['count'];
                        $calendarData[$dateStr]['total_selections'] += (int) $selection['count'];
                    }
                }
            }

            // Fill with incomplete data
            foreach ($incompleteSelections as $incomplete) {
                if ($incomplete['date'] && isset($calendarData[$incomplete['date']])) {
                    $calendarData[$incomplete['date']]['incomplete_count'] = (int) $incomplete['incomplete_count'];
                }
            }

            return [
                'week_start' => $weekStart,
                'week_end' => $endDate->format('Y-m-d'),
                'daily_data' => array_values($calendarData),
                'week_totals' => $this->calculateWeekTotals($calendarData)
            ];
        });
    }

    /**
     * Clear all subscription statistics cache
     */
    public function clearStatisticsCache(): void
    {
        try {
            $patterns = [
                self::CACHE_PREFIX . 'dashboard',
                self::CACHE_PREFIX . 'cuisine_trends_',
                self::CACHE_PREFIX . 'recent_activity_',
                self::CACHE_PREFIX . 'calendar_'
            ];

            foreach ($patterns as $pattern) {
                $this->cache->delete($pattern);
            }

            $this->logger->info('Subscription statistics cache cleared');
        } catch (\Exception $e) {
            $this->logger->error('Failed to clear statistics cache', ['error' => $e->getMessage()]);
        }
    }

    // Private helper methods

    private function getStatusTrends(): array
    {
        // Compare current week with last week
        $thisWeekStart = new \DateTime('monday this week');
        $lastWeekStart = new \DateTime('monday last week');
        $lastWeekEnd = new \DateTime('sunday last week');

        $qb = $this->entityManager->createQueryBuilder();
        $lastWeekCounts = $qb->select('a.statut, COUNT(a.id) as count')
            ->from(Abonnement::class, 'a')
            ->where('a.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $lastWeekStart)
            ->setParameter('end', $lastWeekEnd)
            ->groupBy('a.statut')
            ->getQuery()
            ->getArrayResult();

        // Calculate trend percentages
        $trends = [];
        foreach ($lastWeekCounts as $status) {
            $trends[$status['statut']] = [
                'last_week' => (int) $status['count'],
                'trend' => 'stable' // Will be calculated based on comparison
            ];
        }

        return $trends;
    }

    private function calculateMonthlyProjection(): float
    {
        // Simple projection based on current week revenue
        $weeklyRevenue = $this->getRevenueMetrics()['weekly_total'];
        return $weeklyRevenue * 4.33; // Average weeks per month
    }

    private function calculateRevenueGrowthRate(): float
    {
        // Calculate month-over-month growth
        $thisMonth = $this->getRevenueMetrics()['monthly_total'];
        
        $lastMonthStart = new \DateTime('first day of last month');
        $lastMonthEnd = new \DateTime('last day of last month');
        
        $qb = $this->entityManager->createQueryBuilder();
        $lastMonthRevenue = $qb->select('SUM(s.prix) as total')
            ->from(AbonnementSelection::class, 's')
            ->where('s.dateRepas BETWEEN :start AND :end')
            ->setParameter('start', $lastMonthStart)
            ->setParameter('end', $lastMonthEnd)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        if ($lastMonthRevenue > 0) {
            return round((($thisMonth - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1);
        }

        return 0;
    }

    private function getConversionTrend(): string
    {
        // Simple trend calculation
        $currentRate = $this->getConversionMetrics()['conversion_rate'];
        
        if ($currentRate >= 80) return 'excellent';
        if ($currentRate >= 70) return 'good';
        if ($currentRate >= 60) return 'fair';
        return 'needs_improvement';
    }

    private function getIncompleteSelectionsCount(): int
    {
        $weekStart = new \DateTime('monday this week');
        $weekEnd = new \DateTime('friday this week');
        
        // Count active subscriptions with incomplete selections for this week
        $qb = $this->entityManager->createQueryBuilder();
        return $qb->select('COUNT(DISTINCT a.id)')
            ->from(Abonnement::class, 'a')
            ->leftJoin(AbonnementSelection::class, 's', 'WITH', 'a.id = s.abonnement AND s.dateRepas BETWEEN :start AND :end')
            ->where('a.statut = :actif')
            ->groupBy('a.id')
            ->having('COUNT(s.id) < :expectedMeals')
            ->setParameter('start', $weekStart)
            ->setParameter('end', $weekEnd)
            ->setParameter('actif', 'actif')
            ->setParameter('expectedMeals', 5) // 5 meals per week
            ->getQuery()
            ->getResult();
    }

    private function getPeriodDates(string $period): array
    {
        switch ($period) {
            case 'week':
                return [new \DateTime('monday this week'), new \DateTime('sunday this week')];
            case 'month':
                return [new \DateTime('first day of this month'), new \DateTime('last day of this month')];
            case 'quarter':
                return [new \DateTime('first day of January this year'), new \DateTime('last day of March this year')];
            default:
                return [new \DateTime('monday this week'), new \DateTime('sunday this week')];
        }
    }

    private function getCuisineLabel(string $cuisineType): string
    {
        return match($cuisineType) {
            'marocain' => 'Marocain 🇲🇦',
            'italien' => 'Italien 🇮🇹',
            'international' => 'International 🌍',
            default => $cuisineType
        };
    }

    private function getDayName(string $dayNumber): string
    {
        $days = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
        return $days[(int)$dayNumber] ?? 'Inconnu';
    }

    private function calculateWeekTotals(array $calendarData): array
    {
        $totals = [
            'total_selections' => 0,
            'total_incomplete' => 0,
            'cuisine_totals' => [
                'marocain' => 0,
                'italien' => 0,
                'international' => 0
            ]
        ];

        foreach ($calendarData as $day) {
            $totals['total_selections'] += $day['total_selections'];
            $totals['total_incomplete'] += $day['incomplete_count'];
            
            foreach ($day['cuisine_counts'] as $cuisine => $count) {
                $totals['cuisine_totals'][$cuisine] += $count;
            }
        }

        return $totals;
    }

    private function getFallbackStatistics(): array
    {
        return [
            'overview' => [
                'en_confirmation' => 0,
                'actif' => 0,
                'suspendu' => 0,
                'expire' => 0,
                'annule' => 0,
                'total' => 0
            ],
            'revenue' => [
                'weekly_total' => 0,
                'monthly_total' => 0,
                'average_subscription_value' => 0,
                'currency' => 'MAD'
            ],
            'conversion' => [
                'conversion_rate' => 0,
                'pending_confirmation' => 0
            ],
            'cuisine_trends' => [],
            'alerts' => [],
            'recent_activity' => [],
            'error' => true,
            'generated_at' => (new \DateTime())->format('Y-m-d H:i:s')
        ];
    }
} 