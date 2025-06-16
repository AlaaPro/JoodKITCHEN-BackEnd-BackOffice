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
        $todayActivities = (clone $qb)
            ->select('COUNT(al.id)')
            ->where('DATE(al.loggedAt) = CURRENT_DATE()')
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
     * Get formatted activities for API/JSON response
     */
    public function getFormattedActivities(array $criteria = [], int $limit = 20): array
    {
        $activities = $this->getRecentActivities($limit);
        
        return array_map(function (AuditLog $auditLog) {
            return $this->formatActivityForDisplay($auditLog);
        }, $activities);
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