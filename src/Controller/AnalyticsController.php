<?php

namespace App\Controller;

use App\Service\AnalyticsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/analytics')]
#[IsGranted('ROLE_ADMIN')]
class AnalyticsController extends AbstractController
{
    public function __construct(
        private AnalyticsService $analyticsService,
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Get daily sales report
     */
    #[Route('/daily', name: 'api_analytics_daily', methods: ['GET'])]
    public function getDailySales(Request $request): JsonResponse
    {
        $date = $request->query->get('date');
        $reportDate = $date ? new \DateTime($date) : new \DateTime();

        $report = $this->analyticsService->getDailySalesReport($reportDate);

        return new JsonResponse($report);
    }

    /**
     * Get weekly performance report
     */
    #[Route('/weekly', name: 'api_analytics_weekly', methods: ['GET'])]
    public function getWeeklyReport(Request $request): JsonResponse
    {
        $startDate = $request->query->get('start_date');
        $weekStart = $startDate ? new \DateTime($startDate) : new \DateTime('monday this week');

        $report = $this->analyticsService->getWeeklyReport($weekStart);

        return new JsonResponse($report);
    }

    /**
     * Get customer analytics
     */
    #[Route('/customers', name: 'api_analytics_customers', methods: ['GET'])]
    public function getCustomerAnalytics(): JsonResponse
    {
        $analytics = $this->analyticsService->getCustomerAnalytics();

        return new JsonResponse($analytics);
    }

    /**
     * Get inventory insights
     */
    #[Route('/inventory', name: 'api_analytics_inventory', methods: ['GET'])]
    public function getInventoryInsights(): JsonResponse
    {
        $insights = $this->analyticsService->getInventoryInsights();

        return new JsonResponse($insights);
    }

    /**
     * Get financial summary
     */
    #[Route('/financial', name: 'api_analytics_financial', methods: ['GET'])]
    public function getFinancialSummary(Request $request): JsonResponse
    {
        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');

        $start = $startDate ? new \DateTime($startDate) : new \DateTime('first day of this month');
        $end = $endDate ? new \DateTime($endDate) : new \DateTime();

        $summary = $this->analyticsService->getFinancialSummary($start, $end);

        return new JsonResponse($summary);
    }

    /**
     * Get operational metrics
     */
    #[Route('/operational', name: 'api_analytics_operational', methods: ['GET'])]
    public function getOperationalMetrics(): JsonResponse
    {
        $metrics = $this->analyticsService->getOperationalMetrics();

        return new JsonResponse($metrics);
    }

    /**
     * Get comprehensive dashboard data
     */
    #[Route('/dashboard', name: 'api_analytics_dashboard', methods: ['GET'])]
    public function getDashboard(): JsonResponse
    {
        $today = new \DateTime();
        $thisWeek = new \DateTime('monday this week');
        $thisMonth = new \DateTime('first day of this month');

        $dashboard = [
            'today' => $this->analyticsService->getDailySalesReport($today),
            'this_week' => $this->analyticsService->getWeeklyReport($thisWeek),
            'this_month' => $this->analyticsService->getFinancialSummary($thisMonth),
            'customers' => $this->analyticsService->getCustomerAnalytics(),
            'inventory' => $this->analyticsService->getInventoryInsights(),
            'operational' => $this->analyticsService->getOperationalMetrics(),
            'generated_at' => $today->format('Y-m-d H:i:s')
        ];

        return new JsonResponse($dashboard);
    }

    /**
     * Clear analytics cache
     */
    #[Route('/cache/clear', name: 'api_analytics_cache_clear', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function clearCache(): JsonResponse
    {
        $this->analyticsService->clearAnalyticsCache();

        return new JsonResponse([
            'message' => 'Analytics cache cleared successfully',
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * Export analytics data (for Excel/PDF generation)
     */
    #[Route('/export', name: 'api_analytics_export', methods: ['GET'])]
    public function exportData(Request $request): JsonResponse
    {
        $type = $request->query->get('type', 'daily'); // daily, weekly, monthly
        $format = $request->query->get('format', 'json'); // json, csv
        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');

        $data = [];

        switch ($type) {
            case 'daily':
                $date = $startDate ? new \DateTime($startDate) : new \DateTime();
                $data = $this->analyticsService->getDailySalesReport($date);
                break;

            case 'weekly':
                $start = $startDate ? new \DateTime($startDate) : new \DateTime('monday this week');
                $data = $this->analyticsService->getWeeklyReport($start);
                break;

            case 'financial':
                $start = $startDate ? new \DateTime($startDate) : new \DateTime('first day of this month');
                $end = $endDate ? new \DateTime($endDate) : new \DateTime();
                $data = $this->analyticsService->getFinancialSummary($start, $end);
                break;

            default:
                return new JsonResponse(['error' => 'Invalid export type'], Response::HTTP_BAD_REQUEST);
        }

        if ($format === 'csv') {
            // Convert data to CSV format
            $csvData = $this->convertToCSV($data, $type);
            return new JsonResponse([
                'format' => 'csv',
                'data' => $csvData,
                'filename' => "analytics_{$type}_" . date('Y-m-d') . '.csv'
            ]);
        }

        return new JsonResponse([
            'format' => 'json',
            'type' => $type,
            'data' => $data,
            'exported_at' => (new \DateTime())->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * Get real-time stats for admin monitoring
     */
    #[Route('/realtime', name: 'api_analytics_realtime', methods: ['GET'])]
    public function getRealtimeStats(): JsonResponse
    {
        $today = new \DateTime();
        
        // Get essential real-time data
        $realtime = [
            'current_time' => $today->format('Y-m-d H:i:s'),
            'today_summary' => $this->analyticsService->getDailySalesReport($today)['summary'],
            'operational' => $this->analyticsService->getOperationalMetrics(),
            'last_hour_orders' => $this->getLastHourOrders(),
            'active_users' => $this->getActiveUsersCount()
        ];

        return new JsonResponse($realtime);
    }

    private function convertToCSV(array $data, string $type): string
    {
        $csv = '';
        
        switch ($type) {
            case 'daily':
                $csv .= "Metric,Value\n";
                $csv .= "Date,{$data['date']}\n";
                $csv .= "Total Orders,{$data['summary']['total_orders']}\n";
                $csv .= "Total Revenue,{$data['summary']['total_revenue']}\n";
                $csv .= "Avg Order Value,{$data['summary']['avg_order_value']}\n";
                
                $csv .= "\nPopular Dishes\n";
                $csv .= "Dish Name,Quantity Sold,Revenue\n";
                foreach ($data['popular_dishes'] as $dish) {
                    $csv .= "{$dish['nom']},{$dish['quantity_sold']},{$dish['revenue']}\n";
                }
                break;

            case 'weekly':
                $csv .= "Date,Orders,Revenue\n";
                foreach ($data['daily_breakdown'] as $day) {
                    $csv .= "{$day['date']},{$day['orders']},{$day['revenue']}\n";
                }
                break;

            case 'financial':
                $csv .= "Period: {$data['period']['start']} to {$data['period']['end']}\n";
                $csv .= "Total Revenue,{$data['summary']['total_revenue']}\n";
                $csv .= "Total Orders,{$data['summary']['total_orders']}\n";
                $csv .= "Avg Order Value,{$data['summary']['avg_order_value']}\n";
                
                $csv .= "\nPayment Methods\n";
                $csv .= "Method,Count,Total\n";
                foreach ($data['payment_methods'] as $method) {
                    $csv .= "{$method['methodePaiement']},{$method['count']},{$method['total']}\n";
                }
                break;
        }
        
        return $csv;
    }

    private function getLastHourOrders(): int
    {
        // Quick query for orders in the last hour
        $qb = $this->entityManager->createQueryBuilder();
        
        return (int) $qb->select('COUNT(c.id)')
            ->from('App\Entity\Commande', 'c')
            ->where('c.dateCommande >= :lastHour')
            ->setParameter('lastHour', new \DateTime('-1 hour'))
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function getActiveUsersCount(): int
    {
        // Users who logged in today
        $qb = $this->entityManager->createQueryBuilder();
        
        return (int) $qb->select('COUNT(u.id)')
            ->from('App\Entity\User', 'u')
            ->where('DATE(u.lastConnexion) = CURRENT_DATE()')
            ->getQuery()
            ->getSingleScalarResult();
    }
} 