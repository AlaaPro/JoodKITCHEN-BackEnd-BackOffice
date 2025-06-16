<?php

namespace App\Controller\Admin;

use App\Service\UserActivityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * UserActivityController - Handles user activity tracking endpoints
 */
#[Route('/admin/api/activity', name: 'admin_activity_')]
#[IsGranted('PERM_VIEW_ACTIVITY_LOGS')]
class UserActivityController extends AbstractController
{
    public function __construct(
        private UserActivityService $activityService
    ) {}

    /**
     * Get recent activities for the activity feed
     */
    #[Route('/recent', name: 'recent', methods: ['GET'])]
    public function getRecentActivities(Request $request): JsonResponse
    {
        try {
            $limit = min((int) $request->query->get('limit', 20), 100);
            $activities = $this->activityService->getFormattedActivities([], $limit);

            return new JsonResponse([
                'success' => true,
                'data' => $activities,
                'count' => count($activities)
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la récupération des activités: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get activities for a specific user
     */
    #[Route('/user/{userId}', name: 'user', methods: ['GET'])]
    public function getUserActivities(int $userId, Request $request): JsonResponse
    {
        try {
            $limit = min((int) $request->query->get('limit', 50), 100);
            $activities = $this->activityService->getUserActivities($userId, $limit);
            
            $formattedActivities = array_map(function ($activity) {
                return $this->activityService->formatActivityForDisplay($activity);
            }, $activities);

            return new JsonResponse([
                'success' => true,
                'data' => $formattedActivities,
                'user_id' => $userId,
                'count' => count($formattedActivities)
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la récupération des activités utilisateur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get activities for a specific entity
     */
    #[Route('/entity/{entityClass}/{entityId}', name: 'entity', methods: ['GET'])]
    public function getEntityActivities(string $entityClass, int $entityId, Request $request): JsonResponse
    {
        try {
            // Decode the entity class (it might be URL encoded)
            $entityClass = urldecode($entityClass);
            
            // Validate entity class for security
            $allowedEntities = [
                'App\\Entity\\User',
                'App\\Entity\\AdminProfile',
                'App\\Entity\\Client',
                'App\\Entity\\Order',
                'App\\Entity\\Product',
                'App\\Entity\\Category',
                'App\\Entity\\Permission',
                'App\\Entity\\Role',
            ];
            
            if (!in_array($entityClass, $allowedEntities)) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Type d\'entité non autorisé'
                ], 400);
            }

            $limit = min((int) $request->query->get('limit', 20), 100);
            $activities = $this->activityService->getEntityActivities($entityClass, $entityId, $limit);
            
            $formattedActivities = array_map(function ($activity) {
                return $this->activityService->formatActivityForDisplay($activity);
            }, $activities);

            return new JsonResponse([
                'success' => true,
                'data' => $formattedActivities,
                'entity_class' => $entityClass,
                'entity_id' => $entityId,
                'count' => count($formattedActivities)
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la récupération des activités de l\'entité: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get activity statistics
     */
    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function getActivityStats(): JsonResponse
    {
        try {
            $stats = $this->activityService->getActivityStats();

            return new JsonResponse([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la récupération des statistiques: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get activities by date range
     */
    #[Route('/date-range', name: 'date_range', methods: ['GET'])]
    public function getActivitiesByDateRange(Request $request): JsonResponse
    {
        try {
            $startDate = $request->query->get('start_date');
            $endDate = $request->query->get('end_date');
            
            if (!$startDate || !$endDate) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Les paramètres start_date et end_date sont requis'
                ], 400);
            }

            $startDateTime = new \DateTime($startDate);
            $endDateTime = new \DateTime($endDate);
            $limit = min((int) $request->query->get('limit', 100), 200);

            $activities = $this->activityService->getActivitiesByDateRange($startDateTime, $endDateTime, $limit);
            
            $formattedActivities = array_map(function ($activity) {
                return $this->activityService->formatActivityForDisplay($activity);
            }, $activities);

            return new JsonResponse([
                'success' => true,
                'data' => $formattedActivities,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'count' => count($formattedActivities)
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la récupération des activités par période: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get activities by action type
     */
    #[Route('/action/{action}', name: 'action', methods: ['GET'])]
    public function getActivitiesByAction(string $action, Request $request): JsonResponse
    {
        try {
            // Validate action type
            $allowedActions = ['insert', 'update', 'remove'];
            
            if (!in_array($action, $allowedActions)) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Type d\'action non valide. Actions autorisées: ' . implode(', ', $allowedActions)
                ], 400);
            }

            $limit = min((int) $request->query->get('limit', 50), 100);
            $activities = $this->activityService->getActivitiesByAction($action, $limit);
            
            $formattedActivities = array_map(function ($activity) {
                return $this->activityService->formatActivityForDisplay($activity);
            }, $activities);

            return new JsonResponse([
                'success' => true,
                'data' => $formattedActivities,
                'action' => $action,
                'count' => count($formattedActivities)
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la récupération des activités par action: ' . $e->getMessage()
            ], 500);
        }
    }
} 