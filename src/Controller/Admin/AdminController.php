<?php

namespace App\Controller\Admin;

use App\Entity\KitchenProfile;
use App\Enum\OrderStatus;
use App\Repository\CommandeRepository;
use App\Service\LogSystemService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[Route('/admin')]
class AdminController extends AbstractController
{
    public function __construct(
        private LogSystemService $logSystemService
    ) {}
    /**
     * Admin login page
     */
    #[Route('/login', name: 'admin_login', methods: ['GET'])]
    public function login(): Response
    {
        return $this->render('admin/auth/login.html.twig');
    }

    /**
     * Admin logout
     */
    #[Route('/logout', name: 'admin_logout', methods: ['GET'])]
    public function logout(): void
    {
        // This method can be blank - it will be intercepted by the security system
    }

    /**
     * Admin dashboard
     */
    #[Route('/', name: 'admin_dashboard', methods: ['GET'])]
    #[Route('/dashboard', name: 'admin_dashboard_alt', methods: ['GET'])]
    public function dashboard(): Response
    {
        return $this->render('admin/dashboard/index.html.twig');
    }

    /**
     * Users management
     */
    #[Route('/clients', name: 'admin_clients', methods: ['GET'])]
    public function clients(): Response
    {
        return $this->render('admin/users/clients.html.twig');
    }

    #[Route('/users/create', name: 'admin_users_create', methods: ['GET'])]
    public function createUser(): Response
    {
        return $this->render('admin/users/create.html.twig');
    }

    #[Route('/users/{id}/edit', name: 'admin_users_edit', methods: ['GET'])]
    public function editUser(int $id): Response
    {
        return $this->render('admin/users/edit.html.twig', [
            'userId' => $id
        ]);
    }

    /**
     * Staff management (Admin+ only)
     */
    #[Route('/staff', name: 'admin_staff', methods: ['GET'])]
    public function staff(): Response
    {
        return $this->render('admin/users/staff.html.twig');
    }

    /**
     * Administrators management (Super Admin only)
     */
    #[Route('/admins', name: 'admin_admins', methods: ['GET'])]
    public function admins(): Response
    {
        return $this->render('admin/users/admins.html.twig');
    }

    /**
     * Plats management
     */
    #[Route('/plats', name: 'admin_plats', methods: ['GET'])]
    public function plats(): Response
    {
        return $this->render('admin/menu/plats.html.twig');
    }

    #[Route('/plats/create', name: 'admin_plats_create', methods: ['GET'])]
    public function createPlat(): Response
    {
        return $this->render('admin/menu/create_dish.html.twig');
    }

    #[Route('/plats/{id}/edit', name: 'admin_plats_edit', methods: ['GET'])]
    public function editPlat(int $id): Response
    {
        return $this->render('admin/menu/edit_dish.html.twig', [
            'dishId' => $id
        ]);
    }

    /**
     * Menus management
     */
    #[Route('/menus', name: 'admin_menus', methods: ['GET'])]
    public function menus(): Response
    {
        return $this->render('admin/menu/menus.html.twig');
    }

    #[Route('/menus/create', name: 'admin_menus_create', methods: ['GET'])]
    public function createMenu(): Response
    {
        return $this->render('admin/menu/create_menu.html.twig');
    }

    #[Route('/menus/{id}/edit', name: 'admin_menus_edit', methods: ['GET'])]
    public function editMenu(int $id): Response
    {
        return $this->render('admin/menu/edit_menu.html.twig', [
            'menuId' => $id
        ]);
    }

    /**
     * Categories management
     */
    #[Route('/categories', name: 'admin_categories', methods: ['GET'])]
    public function categories(): Response
    {
        return $this->render('admin/menu/categories.html.twig');
    }

    /**
     * Orders management
     */
    #[Route('/orders', name: 'admin_orders', methods: ['GET'])]
    public function orders(): Response
    {
        // Get OrderStatus choices for the template
        $orderStatuses = array_map(function($status) {
            return [
                'value' => $status->value,
                'label' => $status->getLabel()
            ];
        }, OrderStatus::cases());

        return $this->render('admin/orders/index.html.twig', [
            'order_statuses' => $orderStatuses
        ]);
    }

    #[Route('/orders/{id}', name: 'admin_orders_details', methods: ['GET'])]
    public function orderDetails(int $id): Response
    {
        return $this->render('admin/orders/details.html.twig', [
            'orderId' => $id
        ]);
    }

    #[Route('/orders/tracking', name: 'admin_orders_tracking', methods: ['GET'])]
    public function ordersTracking(): Response
    {
        return $this->render('admin/orders/tracking.html.twig');
    }

    /**
     * Kitchen dashboard (Kitchen staff and Admin+)
     */
    #[Route('/kitchen', name: 'admin_kitchen', methods: ['GET'])]
    public function kitchen(CommandeRepository $commandeRepository, EntityManagerInterface $entityManager): Response
    {
        // Get initial stats for the dashboard
        $stats = $commandeRepository->getOrderStats(true, true); // Include averages, today only
        
        // Get kitchen staff (for team section)
        $kitchenStaff = $entityManager->getRepository(KitchenProfile::class)->findBy(
            ['statutTravail' => 'actif'], 
            ['posteCuisine' => 'ASC']
        );
        
        return $this->render('admin/orders/kitchen.html.twig', [
            'stats' => $stats,
            'kitchen_staff' => $kitchenStaff
        ]);
    }

    /**
     * POS System (Point of Sale)
     */
    #[Route('/pos', name: 'admin_pos', methods: ['GET'])]
    public function pos(): Response
    {
        return $this->render('admin/pos/index.html.twig');
    }

    /**
     * Analytics
     */
    #[Route('/analytics', name: 'admin_analytics_dashboard', methods: ['GET'])]
    public function analyticsDashboard(): Response
    {
        return $this->render('admin/analytics/dashboard.html.twig');
    }

    #[Route('/analytics/sales', name: 'admin_analytics_sales', methods: ['GET'])]
    public function analyticsSales(): Response
    {
        return $this->render('admin/analytics/sales.html.twig');
    }

    #[Route('/analytics/customers', name: 'admin_analytics_customers', methods: ['GET'])]
    public function analyticsCustomers(): Response
    {
        return $this->render('admin/analytics/customers.html.twig');
    }

    #[Route('/analytics/reports', name: 'admin_analytics_reports', methods: ['GET'])]
    public function analyticsReports(): Response
    {
        return $this->render('admin/analytics/reports.html.twig');
    }

    #[Route('/analytics/export', name: 'admin_analytics_export', methods: ['GET'])]
    public function analyticsExport(): Response
    {
        return $this->render('admin/analytics/export.html.twig');
    }

    /**
     * Abonnements (Subscriptions) management - NEW Enhanced System
     */
    #[Route('/abonnements', name: 'admin_abonnements', methods: ['GET'])]
    public function abonnements(): Response
    {
        return $this->render('admin/abonnements/index.html.twig', [
            'page_title' => 'Gestion des Abonnements',
            'api_endpoints' => [
                'subscriptions' => '/admin/abonnements',
                'statistics' => '/admin/abonnements/stats',
                'calendar' => '/admin/abonnements/calendar',
                'pending_count' => '/admin/abonnements/pending-count',
                'status_update' => '/admin/abonnements/status-update',
                'bulk_actions' => '/admin/abonnements/bulk',
                'export' => '/admin/abonnements/export',
                'selections' => '/admin/abonnement-selections',
            ]
        ]);
    }

    #[Route('/abonnements/calendar', name: 'admin_abonnements_calendar', methods: ['GET'])]
    public function abonnementsCalendar(): Response
    {
        return $this->render('admin/abonnements/calendar.html.twig', [
            'page_title' => 'Calendrier des Sélections',
            'api_endpoints' => [
                'calendar_data' => '/admin/abonnements/calendar',
                'meal_selections' => '/admin/abonnement-selections',
                'cuisine_stats' => '/admin/abonnements/cuisine-stats',
            ]
        ]);
    }

    #[Route('/abonnements/{id}', name: 'admin_abonnements_details', methods: ['GET'])]
    public function abonnementDetails(int $id): Response
    {
        return $this->render('admin/abonnements/details.html.twig', [
            'abonnement_id' => $id,
            'page_title' => 'Détails de l\'Abonnement',
            'api_endpoints' => [
                'abonnement' => '/admin/abonnements/' . $id,
                'selections' => '/admin/abonnement-selections?abonnement=' . $id,
                'payments' => '/admin/abonnements/' . $id . '/payments',
                'status_update' => '/admin/abonnements/status-update',
            ]
        ]);
    }

    #[Route('/abonnements/{id}/edit', name: 'admin_abonnements_edit', methods: ['GET'])]
    public function editAbonnement(int $id): Response
    {
        return $this->render('admin/abonnements/edit.html.twig', [
            'abonnement_id' => $id,
            'page_title' => 'Modifier l\'Abonnement'
        ]);
    }

    /**
     * Legacy subscriptions route - keep for backward compatibility
     */
    #[Route('/subscriptions', name: 'admin_subscriptions', methods: ['GET'])]
    public function subscriptions(): Response
    {
        // Redirect to new abonnements route
        return $this->redirectToRoute('admin_abonnements');
    }

    /**
     * Notifications
     */
    #[Route('/notifications', name: 'admin_notifications', methods: ['GET'])]
    public function notifications(): Response
    {
        return $this->render('admin/notifications/index.html.twig');
    }

    /**
     * Settings (Admin+ only)
     */
    #[Route('/settings', name: 'admin_settings', methods: ['GET'])]
    public function settings(): Response
    {
        return $this->render('admin/settings/index.html.twig');
    }

    #[Route('/settings/account', name: 'admin_settings_account', methods: ['GET'])]
    public function settingsAccount(): Response
    {
        return $this->render('admin/settings/account.html.twig');
    }

    /**
     * User profile
     */
    #[Route('/profile', name: 'admin_profile', methods: ['GET'])]
    public function profile(): Response
    {
        return $this->render('admin/profile/index.html.twig');
    }

    /**
     * System management (Super Admin only)
     */
    #[Route('/system/logs', name: 'admin_system_logs', methods: ['GET'])]
    public function systemLogs(): Response
    {
        // The template will load data dynamically via JavaScript API calls
        // but we can pass some initial metadata if needed
        return $this->render('admin/system/logs.html.twig', [
            'page_title' => 'Logs Système',
            'api_endpoints' => [
                'stats' => $this->generateUrl('api_admin_logs_stats'),
                'logs' => $this->generateUrl('api_admin_logs'),
                'recent' => $this->generateUrl('api_admin_logs_recent'),
                'errors' => $this->generateUrl('api_admin_logs_errors'),
                'distribution' => $this->generateUrl('api_admin_logs_distribution'),
                'health' => $this->generateUrl('api_admin_system_health'),
                'export' => $this->generateUrl('api_admin_logs_export')
            ]
        ]);
    }

    #[Route('/system/cache', name: 'admin_system_cache', methods: ['GET'])]
    public function systemCache(): Response
    {
        return $this->render('admin/system/cache.html.twig');
    }

    #[Route('/system/backup', name: 'admin_system_backup', methods: ['GET'])]
    public function systemBackup(): Response
    {
        return $this->render('admin/system/backup.html.twig');
    }

    #[Route('/system/user-activities', name: 'admin_system_user_activities', methods: ['GET'])]
    public function systemUserActivities(): Response
    {
        return $this->render('admin/system/user-activities.html.twig', [
            'page_title' => 'Activités Utilisateurs',
            'api_endpoints' => [
                'stats' => $this->generateUrl('api_admin_activities_stats'),
                'activities' => $this->generateUrl('api_admin_activities'),
                'recent' => $this->generateUrl('api_admin_activities_recent'),
                'distribution' => $this->generateUrl('api_admin_activities_distribution'),
                'profiles' => $this->generateUrl('api_admin_activities_profiles'),
                'export' => $this->generateUrl('api_admin_activities_export')
            ]
        ]);
    }

    /**
     * Search functionality
     */
    #[Route('/search', name: 'admin_search', methods: ['GET'])]
    public function search(Request $request): Response
    {
        $query = $request->query->get('q', '');
        
        return $this->render('admin/search/results.html.twig', [
            'query' => $query
        ]);
    }

    /**
     * Password recovery
     */
    #[Route('/forgot-password', name: 'admin_forgot_password', methods: ['GET'])]
    public function forgotPassword(): Response
    {
        return $this->render('admin/auth/forgot.html.twig');
    }

    /**
     * Error handler for admin area
     */
    public function handleAdminError(\Throwable $exception): Response
    {
        // Log the error
        // Return appropriate error page
        
        return $this->render('admin/error/500.html.twig', [
            'exception' => $exception
        ]);
    }
} 