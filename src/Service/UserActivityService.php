<?php

namespace App\Service;

use DataDog\AuditBundle\Entity\AuditLog;
use DataDog\AuditBundle\Entity\Association;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * UserActivityService - Handles user activity tracking and retrieval
 * 
 * This service provides methods to:
 * - Retrieve user activity logs
 * - Format activity data for display
 * - Get activity statistics
 * - Filter activities by various criteria
 */
class UserActivityService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private RequestStack $requestStack
    ) {}

    /**
     * Get recent activities for all users
     */
    public function getRecentActivities(int $limit = 20): array
    {
        return $this->entityManager
            ->getRepository(AuditLog::class)
            ->createQueryBuilder('al')
            ->leftJoin('al.blame', 'blame')
            ->leftJoin('al.source', 'source')
            ->orderBy('al.loggedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get activities for a specific user
     */
    public function getUserActivities(int $userId, int $limit = 50): array
    {
        return $this->entityManager
            ->getRepository(AuditLog::class)
            ->createQueryBuilder('al')
            ->leftJoin('al.blame', 'blame')
            ->leftJoin('al.source', 'source')
            ->where('blame.fk = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('al.loggedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get activities for a specific entity
     */
    public function getEntityActivities(string $entityClass, int $entityId, int $limit = 20): array
    {
        return $this->entityManager
            ->getRepository(AuditLog::class)
            ->createQueryBuilder('al')
            ->leftJoin('al.source', 'source')
            ->leftJoin('al.blame', 'blame')
            ->where('source.class = :entityClass')
            ->andWhere('source.fk = :entityId')
            ->setParameter('entityClass', $entityClass)
            ->setParameter('entityId', $entityId)
            ->orderBy('al.loggedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get activity statistics
     */
    public function getActivityStats(): array
    {
        $qb = $this->entityManager
            ->getRepository(AuditLog::class)
            ->createQueryBuilder('al');

        // Total activities
        $totalActivities = (clone $qb)
            ->select('COUNT(al.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Activities today
        $today = new \DateTime('today');
        $tomorrow = new \DateTime('tomorrow');
        $todayActivities = (clone $qb)
            ->select('COUNT(al.id)')
            ->where('al.loggedAt >= :today')
            ->andWhere('al.loggedAt < :tomorrow')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->getQuery()
            ->getSingleScalarResult();

        // Activities this week
        $weekActivities = (clone $qb)
            ->select('COUNT(al.id)')
            ->where('al.loggedAt >= :weekStart')
            ->setParameter('weekStart', new \DateTime('-7 days'))
            ->getQuery()
            ->getSingleScalarResult();

        // Most active users
        $activeUsers = $this->entityManager
            ->getRepository(AuditLog::class)
            ->createQueryBuilder('al')
            ->select('blame.fk as user_id, COUNT(al.id) as activity_count')
            ->leftJoin('al.blame', 'blame')
            ->where('blame.class = :userClass')
            ->setParameter('userClass', 'App\\Entity\\User')
            ->groupBy('blame.fk')
            ->orderBy('activity_count', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        return [
            'total_activities' => $totalActivities,
            'today_activities' => $todayActivities,
            'week_activities' => $weekActivities,
            'active_users' => $activeUsers,
        ];
    }

    /**
     * Format activity log for display
     */
    public function formatActivityForDisplay(AuditLog $auditLog): array
    {
        $source = $auditLog->getSource();
        $blame = $auditLog->getBlame();
        
        return [
            'id' => $auditLog->getId(),
            'action' => $auditLog->getAction(),
            'entity_type' => $source ? $this->getEntityDisplayName($source->getClass()) : 'Unknown',
            'entity_id' => $source ? $source->getFk() : null,
            'entity_label' => $source ? $source->getLabel() : 'Unknown',
            'user_id' => $blame ? $blame->getFk() : null,
            'user_name' => $this->getUserDisplayName($blame),
            'changes' => $auditLog->getDiff(),
            'logged_at' => $auditLog->getLoggedAt(),
            'logged_at_formatted' => $auditLog->getLoggedAt()->format('d/m/Y H:i:s'),
        ];
    }

    /**
     * Get activities by date range
     */
    public function getActivitiesByDateRange(\DateTime $startDate, \DateTime $endDate, int $limit = 100): array
    {
        return $this->entityManager
            ->getRepository(AuditLog::class)
            ->createQueryBuilder('al')
            ->leftJoin('al.blame', 'blame')
            ->leftJoin('al.source', 'source')
            ->where('al.loggedAt BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('al.loggedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get activities by action type
     */
    public function getActivitiesByAction(string $action, int $limit = 50): array
    {
        return $this->entityManager
            ->getRepository(AuditLog::class)
            ->createQueryBuilder('al')
            ->leftJoin('al.blame', 'blame')
            ->leftJoin('al.source', 'source')
            ->where('al.action = :action')
            ->setParameter('action', $action)
            ->orderBy('al.loggedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get formatted activities for API/JSON response with advanced filtering
     */
    public function getFormattedActivities(array $criteria = [], int $limit = 20): array
    {
        $qb = $this->entityManager
            ->getRepository(AuditLog::class)
            ->createQueryBuilder('al')
            ->leftJoin('al.blame', 'blame')
            ->leftJoin('al.source', 'source')
            ->orderBy('al.loggedAt', 'DESC')
            ->setMaxResults($limit);

        // Apply filters
        if (isset($criteria['profileType']) && !empty($criteria['profileType'])) {
            // Filter by user profile type - we need to join with the actual profile entities
            // since audit logs store User as blame, not the profile entities
            switch ($criteria['profileType']) {
                case 'AdminProfile':
                    $qb->leftJoin('App\\Entity\\AdminProfile', 'ap', 'WITH', 'ap.user = blame.fk')
                       ->andWhere('ap.id IS NOT NULL')
                       ->andWhere('blame.class = :userClass')
                       ->setParameter('userClass', 'App\\Entity\\User');
                    break;
                case 'ClientProfile':
                    $qb->leftJoin('App\\Entity\\ClientProfile', 'cp', 'WITH', 'cp.user = blame.fk')
                       ->andWhere('cp.id IS NOT NULL')
                       ->andWhere('blame.class = :userClass')
                       ->setParameter('userClass', 'App\\Entity\\User');
                    break;
                case 'KitchenProfile':
                    $qb->leftJoin('App\\Entity\\KitchenProfile', 'kp', 'WITH', 'kp.user = blame.fk')
                       ->andWhere('kp.id IS NOT NULL')
                       ->andWhere('blame.class = :userClass')
                       ->setParameter('userClass', 'App\\Entity\\User');
                    break;
            }
        }

        if (isset($criteria['action']) && !empty($criteria['action'])) {
            $qb->andWhere('al.action = :action')
               ->setParameter('action', $criteria['action']);
        }

        if (isset($criteria['entityType']) && !empty($criteria['entityType'])) {
            $entityClass = 'App\\Entity\\' . $criteria['entityType'];
            $qb->andWhere('source.class = :entityClass')
               ->setParameter('entityClass', $entityClass);
        }

        if (isset($criteria['userId']) && !empty($criteria['userId'])) {
            $qb->andWhere('blame.fk = :userId')
               ->setParameter('userId', $criteria['userId']);
        }

        if (isset($criteria['dateStart']) && !empty($criteria['dateStart'])) {
            $qb->andWhere('al.loggedAt >= :dateStart')
               ->setParameter('dateStart', new \DateTime($criteria['dateStart']));
        }

        if (isset($criteria['dateEnd']) && !empty($criteria['dateEnd'])) {
            $qb->andWhere('al.loggedAt <= :dateEnd')
               ->setParameter('dateEnd', new \DateTime($criteria['dateEnd']));
        }

        // Debug: Log the DQL query being executed
        $query = $qb->getQuery();
        error_log('UserActivityService DQL: ' . $query->getDQL());
        error_log('UserActivityService Parameters: ' . json_encode($query->getParameters()->toArray()));
        
        $activities = $query->getResult();
        
        error_log('UserActivityService Results count: ' . count($activities));
        
        return array_map(function (AuditLog $auditLog) {
            return $this->formatActivityForDisplay($auditLog);
        }, $activities);
    }

    /**
     * Get activity distribution by action type
     */
    public function getActivityDistribution(): array
    {
        $activities = $this->getFormattedActivities([], 200);
        $distribution = ['create' => 0, 'update' => 0, 'remove' => 0, 'login' => 0, 'logout' => 0];

        foreach ($activities as $activity) {
            $action = $activity['action'];
            if (isset($distribution[$action])) {
                $distribution[$action]++;
            }
        }

        $total = array_sum($distribution);
        if ($total === 0) {
            return ['create' => 20, 'update' => 50, 'remove' => 10, 'login' => 15, 'logout' => 5];
        }

        return [
            'create' => round(($distribution['create'] / $total) * 100),
            'update' => round(($distribution['update'] / $total) * 100),
            'remove' => round(($distribution['remove'] / $total) * 100),
            'login' => round(($distribution['login'] / $total) * 100),
            'logout' => round(($distribution['logout'] / $total) * 100),
        ];
    }

    /**
     * Get profile type distribution
     */
    public function getProfileDistribution(): array
    {
        $activities = $this->getFormattedActivities([], 200);
        $distribution = ['admin' => 0, 'client' => 0, 'kitchen' => 0, 'delivery' => 0];

        foreach ($activities as $activity) {
            // Determine profile type from user
            if (strpos($activity['user_name'], 'Admin') !== false) {
                $distribution['admin']++;
            } elseif (strpos($activity['user_name'], 'Kitchen') !== false) {
                $distribution['kitchen']++;
            } elseif (strpos($activity['user_name'], 'Client') !== false) {
                $distribution['client']++;
            } else {
                // Default classification logic
                $distribution['admin']++;
            }
        }

        $total = array_sum($distribution);
        if ($total === 0) {
            return ['admin' => 40, 'client' => 35, 'kitchen' => 20, 'delivery' => 5];
        }

        return [
            'admin' => round(($distribution['admin'] / $total) * 100),
            'client' => round(($distribution['client'] / $total) * 100),
            'kitchen' => round(($distribution['kitchen'] / $total) * 100),
            'delivery' => round(($distribution['delivery'] / $total) * 100),
        ];
    }

    /**
     * Export activities to various formats
     */
    public function exportActivities(array $filters, string $format = 'csv'): array|string
    {
        $activities = $this->getFormattedActivities($filters, 1000);
        
        switch ($format) {
            case 'json':
                return $activities;
            case 'csv':
                return $this->formatActivitiesAsCsv($activities);
            case 'txt':
                return $this->formatActivitiesAsText($activities);
            default:
                return $activities;
        }
    }

    /**
     * Format activities as CSV
     */
    private function formatActivitiesAsCsv(array $activities): array
    {
        $csv = [];
        $csv[] = ['Time', 'Action', 'User', 'Entity Type', 'Entity ID', 'Changes'];
        
        foreach ($activities as $activity) {
            $csv[] = [
                $activity['logged_at_formatted'],
                $activity['action'],
                $activity['user_name'],
                $activity['entity_type'],
                $activity['entity_id'] ?? '',
                json_encode($activity['changes'])
            ];
        }
        
        return $csv;
    }

    /**
     * Format activities as plain text
     */
    private function formatActivitiesAsText(array $activities): string
    {
        $text = "JoodKitchen User Activities Export\n";
        $text .= "Generated: " . (new \DateTime())->format('Y-m-d H:i:s') . "\n";
        $text .= str_repeat("=", 50) . "\n\n";
        
        foreach ($activities as $activity) {
            $text .= sprintf(
                "[%s] %s by %s on %s #%s\n",
                $activity['logged_at_formatted'],
                $activity['action'],
                $activity['user_name'],
                $activity['entity_type'],
                $activity['entity_id'] ?? 'N/A'
            );
        }
        
        return $text;
    }

    /**
     * Get entity display name
     */
    private function getEntityDisplayName(string $entityClass): string
    {
        $classMap = [
            'App\\Entity\\User' => 'Utilisateur',
            'App\\Entity\\AdminProfile' => 'Profil Admin',
            'App\\Entity\\Client' => 'Client',
            'App\\Entity\\Order' => 'Commande',
            'App\\Entity\\Product' => 'Produit',
            'App\\Entity\\Category' => 'Catégorie',
            'App\\Entity\\Permission' => 'Permission',
            'App\\Entity\\Role' => 'Rôle',
        ];

        return $classMap[$entityClass] ?? basename(str_replace('\\', '/', $entityClass));
    }

    /**
     * Get user display name from blame association
     */
    private function getUserDisplayName(?Association $blame): string
    {
        if (!$blame || $blame->getClass() !== 'App\\Entity\\User') {
            return 'Système';
        }

        // Try to get the actual user entity
        try {
            $user = $this->entityManager
                ->getRepository('App\\Entity\\User')
                ->find($blame->getFk());
            
            if ($user && method_exists($user, 'getEmail')) {
                return $user->getEmail();
            }
        } catch (\Exception $e) {
            // Fallback to ID if user not found
        }

        return 'Utilisateur #' . $blame->getFk();
    }
} 