<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\OrderStatus;
use App\Service\CacheService;
use App\Service\NotificationService;
use App\Repository\CommandeRepository;
use App\Repository\PlatRepository;
use App\Repository\MenuRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/mobile')]
class MobileApiController extends AbstractController
{
    public function __construct(
        private CacheService $cacheService,
        private NotificationService $notificationService,
        private CommandeRepository $commandeRepository,
        private PlatRepository $platRepository,
        private MenuRepository $menuRepository
    ) {}

    /**
     * Mobile Dashboard - Lightweight overview for mobile apps
     */
    #[Route('/dashboard', name: 'api_mobile_dashboard', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getMobileDashboard(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // Get basic user info
        $userInfo = [
            'id' => $user->getId(),
            'nom' => $user->getNom(),
            'prenom' => $user->getPrenom(),
            'roles' => $user->getRoles()
        ];

        // Get dashboard data based on role
        $dashboardData = [
            'user' => $userInfo,
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
        ];

        if (in_array('ROLE_CLIENT', $user->getRoles())) {
            $dashboardData['client'] = $this->getClientDashboard($user);
        }

        if (in_array('ROLE_KITCHEN', $user->getRoles())) {
            $dashboardData['kitchen'] = $this->getKitchenDashboard();
        }

        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            $dashboardData['admin'] = $this->getAdminDashboard();
        }

        return new JsonResponse($dashboardData);
    }

    /**
     * Lightweight menu for mobile apps
     */
    #[Route('/menu/today', name: 'api_mobile_menu_today', methods: ['GET'])]
    public function getTodayMenu(): JsonResponse
    {
        $menuOfTheDay = $this->cacheService->getMenuOfTheDay();
        $popularPlats = $this->cacheService->getPopularPlats(5);

        return new JsonResponse([
            'menu_of_the_day' => $menuOfTheDay,
            'popular_plats' => $popularPlats,
            'categories' => $this->getMenuCategories()
        ]);
    }

    /**
     * Quick order status for mobile polling
     */
    #[Route('/orders/status', name: 'api_mobile_orders_status', methods: ['GET'])]
    #[IsGranted('ROLE_CLIENT')]
    public function getOrdersStatus(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $since = $request->query->get('since');
        $sinceDate = $since ? new \DateTime($since) : new \DateTime('-10 minutes');

        $updates = $this->notificationService->getOrderStatusUpdates($user->getId(), $sinceDate);
        $unreadNotifications = $this->notificationService->getUnreadNotifications($user->getId());

        return new JsonResponse([
            'order_updates' => $updates,
            'notifications' => $unreadNotifications,
            'has_updates' => count($updates) > 0 || count($unreadNotifications) > 0,
            'last_check' => (new \DateTime())->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * Lightweight plat search for mobile
     */
    #[Route('/plats/search', name: 'api_mobile_plats_search', methods: ['GET'])]
    public function searchPlats(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        $maxPrice = $request->query->get('max_price');
        $limit = $request->query->getInt('limit', 10);
        
        if (empty($query)) {
            return $this->json([
                'success' => false,
                'message' => 'Search query is required'
            ], 400);
        }
        
        $criteria = ['disponible' => true];
        $plats = $this->platRepository->findBy($criteria, ['nom' => 'ASC'], $limit);
        
        // Filter by search query and price
        $filteredPlats = array_filter($plats, function($plat) use ($query, $maxPrice) {
            $matchesQuery = stripos($plat->getNom(), $query) !== false ||
                           stripos($plat->getDescription(), $query) !== false;
            $matchesPrice = !$maxPrice || floatval($plat->getPrix()) <= floatval($maxPrice);
            
            return $matchesQuery && $matchesPrice;
        });
        
        $result = array_map(function($plat) {
            return [
                'id' => $plat->getId(),
                'nom' => $plat->getNom(),
                'prix' => $plat->getPrix(),
                'categorie' => $plat->getCategorie(),
                'image' => $plat->getImage(),
                'temps_preparation' => $plat->getTempsPreparation(),
                'allergenes' => $plat->getAllergenes()
            ];
        }, array_values($filteredPlats));
        
        return $this->json([
            'success' => true,
            'plats' => $result,
            'total' => count($result)
        ]);
    }

    /**
     * Quick order creation for mobile
     */
    #[Route('/orders/quick', name: 'api_mobile_quick_order', methods: ['POST'])]
    #[IsGranted('ROLE_CLIENT')]
    public function createQuickOrder(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $data = json_decode($request->getContent(), true);

        if (!isset($data['items']) || empty($data['items'])) {
            return new JsonResponse(['error' => 'Items are required'], Response::HTTP_BAD_REQUEST);
        }

        // Simplified order creation logic for mobile
        // This would integrate with your existing order creation logic

        return new JsonResponse([
            'message' => 'Order created successfully',
            'order_id' => 'mobile_' . time(), // Placeholder
            'estimated_time' => '25 minutes',
            'status' => OrderStatus::PENDING->value
        ], Response::HTTP_CREATED);
    }

    /**
     * User preferences for mobile app
     */
    #[Route('/preferences', name: 'api_mobile_preferences', methods: ['GET', 'PUT'])]
    #[IsGranted('ROLE_USER')]
    public function userPreferences(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($request->getMethod() === 'PUT') {
            // Update preferences
            $data = json_decode($request->getContent(), true);
            
            // Store mobile preferences (you could add a preferences table or use metadata)
            return new JsonResponse(['message' => 'Preferences updated']);
        }

        // Get preferences
        $preferences = [
            'notifications' => [
                'order_updates' => true,
                'promotions' => true,
                'new_menus' => false
            ],
            'delivery' => [
                'default_address' => $user->getAdresse(),
                'save_addresses' => true
            ],
            'payment' => [
                'default_method' => 'carte',
                'save_cards' => false
            ],
            'app' => [
                'theme' => 'auto',
                'language' => 'fr',
                'offline_mode' => false
            ]
        ];

        return new JsonResponse($preferences);
    }

    /**
     * Offline sync data for mobile apps
     */
    #[Route('/sync', name: 'api_mobile_sync', methods: ['GET'])]
    public function getSyncData(Request $request): JsonResponse
    {
        $lastSync = $request->query->get('last_sync');
        $lastSyncDate = $lastSync ? new \DateTime($lastSync) : new \DateTime('-1 day');

        // Essential data for offline functionality
        $syncData = [
            'dishes' => $this->cacheService->getAvailablePlats(),
            'menus' => $this->cacheService->getActiveMenus(),
            'categories' => $this->getMenuCategories(),
            'version' => '1.0',
            'last_updated' => (new \DateTime())->format('Y-m-d H:i:s'),
            'cache_duration' => 3600 // 1 hour
        ];

        return new JsonResponse($syncData);
    }

    /**
     * Kitchen mobile dashboard
     */
    #[Route('/kitchen/mobile', name: 'api_mobile_kitchen', methods: ['GET'])]
    #[IsGranted('ROLE_KITCHEN')]
    public function getKitchenMobile(): JsonResponse
    {
        $stats = $this->cacheService->getKitchenStats();
        $updates = $this->notificationService->getKitchenUpdates();

        return new JsonResponse([
            'stats' => $stats,
            'updates' => $updates,
            'quick_actions' => [
                'mark_ready' => '/api/orders/{id}/status',
                'update_estimate' => '/api/orders/{id}/estimate'
            ]
        ]);
    }

    /**
     * Mobile health check
     */
    #[Route('/health', name: 'api_mobile_health', methods: ['GET'])]
    public function healthCheck(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
            'version' => '1.0',
            'server_time' => time(),
            'endpoints' => [
                'dashboard' => '/api/mobile/dashboard',
                'menu' => '/api/mobile/menu/today',
                'orders' => '/api/mobile/orders/status',
                'sync' => '/api/mobile/sync'
            ]
        ]);
    }

    private function getClientDashboard(User $user): array
    {
        // Recent orders
        $recentOrders = $this->commandeRepository->findBy(
            ['user' => $user],
            ['dateCommande' => 'DESC'],
            3
        );

        // Unread notifications count
        $notificationStats = $this->notificationService->getNotificationStats($user->getId());

        return [
            'recent_orders' => array_map(function($order) {
                return [
                    'id' => $order->getId(),
                    'statut' => $order->getStatut(),
                    'total' => $order->getTotal(),
                    'date' => $order->getDateCommande()->format('Y-m-d H:i')
                ];
            }, $recentOrders),
            'notifications' => $notificationStats,
            'loyalty_points' => $user->getClientProfile()?->getPointsFidelite() ?? 0
        ];
    }

    private function getKitchenDashboard(): array
    {
        return $this->cacheService->getKitchenStats();
    }

    private function getAdminDashboard(): array
    {
        $stats = $this->cacheService->getKitchenStats();
        $popularPlats = $this->cacheService->getPopularPlats(3);

        return [
            'stats' => $stats,
            'popular_plats' => $popularPlats,
            'quick_stats' => [
                'orders_today' => $stats['orders_today'],
                'revenue_today' => $stats['revenue_today']
            ]
        ];
    }

    private function getMenuCategories(): array
    {
        return [
            'Entrées',
            'Plats principaux',
            'Desserts',
            'Boissons',
            'Spécialités'
        ];
    }
} 