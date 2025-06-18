<?php

namespace App\Service;

use DataDog\AuditBundle\Entity\AuditLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;
use Psr\Log\LoggerInterface;

/**
 * LogSystemService - Comprehensive system logging and audit management
 * 
 * This service provides methods to:
 * - Retrieve system and audit logs with filtering
 * - Generate log statistics and analytics
 * - Map audit actions to system log levels
 * - Provide real-time log data for the admin interface
 */
class LogSystemService
{
    private array $componentMapping = [
        'User' => 'auth',
        'AdminProfile' => 'admin',
        'Client' => 'customers',
        'Order' => 'orders',
        'Commande' => 'orders',
        'CommandeArticle' => 'orders',
        'Product' => 'menu',
        'Plat' => 'menu',
        'Menu' => 'menu',
        'Category' => 'menu',
        'Payment' => 'payment',
        'KitchenProfile' => 'kitchen',
        'Permission' => 'security',
        'Role' => 'security',
    ];

    private array $actionLevelMapping = [
        'create' => 'info',
        'update' => 'info',
        'remove' => 'warning',
        'delete' => 'warning',
        'login' => 'info',
        'logout' => 'info',
        'failed_login' => 'error',
        'security_violation' => 'error',
        'payment_failed' => 'error',
        'payment_success' => 'info',
        'order_cancelled' => 'warning',
        'system_error' => 'error',
        'constraint_violation' => 'error',
        'database_error' => 'error',
        'validation_error' => 'error',
        'permission_denied' => 'error',
        'authentication_failed' => 'error',
        'invalid_operation' => 'error',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private LoggerInterface $logger
    ) {}

    /**
     * Get log statistics for dashboard
     */
    public function getLogStatistics(): array
    {
        $today = new \DateTime('today');
        $tomorrow = new \DateTime('tomorrow');
        $qb = $this->entityManager->getRepository(AuditLog::class)->createQueryBuilder('al');

        // Total logs today
        $totalToday = (clone $qb)
            ->select('COUNT(al.id)')
            ->where('al.loggedAt >= :today')
            ->andWhere('al.loggedAt < :tomorrow')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->getQuery()
            ->getSingleScalarResult();

        // Simulated error/warning counts based on audit actions
        $recentLogs = $this->getFormattedAuditLogs(['limit' => 100]);
        
        $errorCount = 0;
        $warningCount = 0;
        $infoCount = 0;

        foreach ($recentLogs as $log) {
            switch ($log['level']) {
                case 'error':
                    $errorCount++;
                    break;
                case 'warning':
                    $warningCount++;
                    break;
                case 'info':
                    $infoCount++;
                    break;
            }
        }

        return [
            'logs_today' => $totalToday,
            'errors' => $errorCount,
            'warnings' => $warningCount,
            'info' => $infoCount,
            'total_audit_logs' => count($recentLogs)
        ];
    }

    /**
     * Get formatted audit logs with filtering
     */
    public function getFormattedAuditLogs(array $filters = []): array
    {
        $qb = $this->entityManager->getRepository(AuditLog::class)->createQueryBuilder('al')
            ->leftJoin('al.blame', 'blame')
            ->leftJoin('al.source', 'source')
            ->orderBy('al.loggedAt', 'DESC');

        // Apply filters
        if (isset($filters['level'])) {
            // We'll filter by level after mapping actions to levels
        }

        if (isset($filters['component'])) {
            $qb->andWhere('source.class LIKE :component')
               ->setParameter('component', '%' . $filters['component'] . '%');
        }

        if (isset($filters['dateStart'])) {
            $qb->andWhere('al.loggedAt >= :dateStart')
               ->setParameter('dateStart', new \DateTime($filters['dateStart']));
        }

        if (isset($filters['dateEnd'])) {
            $qb->andWhere('al.loggedAt <= :dateEnd')
               ->setParameter('dateEnd', new \DateTime($filters['dateEnd']));
        }

        if (isset($filters['limit'])) {
            $qb->setMaxResults($filters['limit']);
        } else {
            $qb->setMaxResults(50);
        }

        $auditLogs = $qb->getQuery()->getResult();
        $formattedLogs = [];

        foreach ($auditLogs as $auditLog) {
            $formatted = $this->formatAuditLogForSystem($auditLog);
            
            // Apply level filter if specified
            if (isset($filters['level']) && $formatted['level'] !== $filters['level']) {
                continue;
            }

            $formattedLogs[] = $formatted;
        }

        return $formattedLogs;
    }

    /**
     * Format audit log for system display
     */
    private function formatAuditLogForSystem(AuditLog $auditLog): array
    {
        $source = $auditLog->getSource();
        $blame = $auditLog->getBlame();
        $action = $auditLog->getAction();

        // Determine component based on entity class
        $entityClass = $source ? $source->getClass() : 'Unknown';
        $entityName = $this->getEntityDisplayName($entityClass);
        $component = $this->getComponentFromEntity($entityClass);

        // Determine log level based on action
        $level = $this->actionLevelMapping[$action] ?? 'info';

        // Generate meaningful message
        $message = $this->generateLogMessage($action, $entityName, $source, $blame);

        return [
            'id' => $auditLog->getId(),
            'timestamp' => $auditLog->getLoggedAt()->format('H:i:s.v'),
            'time_full' => $auditLog->getLoggedAt()->format('Y-m-d H:i:s'),
            'level' => $level,
            'component' => $component,
            'message' => $message,
            'action' => $action,
            'entity_type' => $entityName,
            'entity_id' => $source ? $source->getFk() : null,
            'user_name' => $this->getUserDisplayName($blame),
            'changes' => $auditLog->getDiff(),
            'logged_at' => $auditLog->getLoggedAt()
        ];
    }

    /**
     * Generate meaningful log messages
     */
    private function generateLogMessage(string $action, string $entityName, $source, $blame): string
    {
        $userName = $this->getUserDisplayName($blame);
        $entityId = $source ? "#" . $source->getFk() : '';

        switch ($action) {
            case 'create':
                return "Création {$entityName} {$entityId} par {$userName}";
            case 'update':
                return "Modification {$entityName} {$entityId} par {$userName}";
            case 'remove':
            case 'delete':
                return "Suppression {$entityName} {$entityId} par {$userName}";
            default:
                return "Action '{$action}' sur {$entityName} {$entityId} par {$userName}";
        }
    }

    /**
     * Get component from entity class
     */
    private function getComponentFromEntity(string $entityClass): string
    {
        $shortName = substr($entityClass, strrpos($entityClass, '\\') + 1);
        return $this->componentMapping[$shortName] ?? 'system';
    }

    /**
     * Get entity display name
     */
    private function getEntityDisplayName(string $entityClass): string
    {
        $shortName = substr($entityClass, strrpos($entityClass, '\\') + 1);
        
        $displayNames = [
            'User' => 'Utilisateur',
            'AdminProfile' => 'Admin',
            'Client' => 'Client',
            'Order' => 'Commande',
            'Commande' => 'Commande',
            'CommandeArticle' => 'Article commande',
            'Product' => 'Produit',
            'Plat' => 'Plat',
            'Menu' => 'Menu',
            'Category' => 'Catégorie',
            'Payment' => 'Paiement',
            'KitchenProfile' => 'Staff cuisine',
            'Permission' => 'Permission',
            'Role' => 'Rôle',
        ];

        return $displayNames[$shortName] ?? $shortName;
    }

    /**
     * Get user display name from blame
     */
    private function getUserDisplayName($blame): string
    {
        if (!$blame) {
            return 'Système';
        }

        // Try to get actual user entity
        try {
            $userRepository = $this->entityManager->getRepository('App\Entity\User');
            $user = $userRepository->find($blame->getFk());
            
            if ($user) {
                return $user->getPrenom() . ' ' . $user->getNom();
            }
        } catch (\Exception $e) {
            // Fallback to blame label
        }

        return $blame->getLabel() ?: "Utilisateur #{$blame->getFk()}";
    }

    /**
     * Get recent error logs for sidebar
     */
    public function getRecentErrors(int $limit = 5): array
    {
        // First try to get actual error-level logs
        $errorLogs = $this->getFormattedAuditLogs(['level' => 'error', 'limit' => $limit]);
        
        // If no error logs, get warning-level logs as fallback
        if (empty($errorLogs)) {
            $warningLogs = $this->getFormattedAuditLogs(['level' => 'warning', 'limit' => $limit]);
            $errorLogs = array_slice($warningLogs, 0, $limit);
        }
        
        // If still empty, get most recent audit logs and treat them as potential issues
        if (empty($errorLogs)) {
            $recentLogs = $this->getFormattedAuditLogs(['limit' => $limit * 2]);
            
            // Look for patterns that might indicate errors
            foreach ($recentLogs as $log) {
                if ($this->isErrorPattern($log)) {
                    $errorLogs[] = $log;
                    if (count($errorLogs) >= $limit) {
                        break;
                    }
                }
            }
        }
        
        $errors = [];
        foreach ($errorLogs as $log) {
            $errors[] = [
                'title' => $this->getErrorTitle($log),
                'time' => $log['timestamp'],
                'component' => $log['component'],
                'count' => 1,
                'severity' => $log['level']
            ];
        }

        return $errors;
    }

    /**
     * Check if a log entry matches error patterns
     */
    private function isErrorPattern(array $log): bool
    {
        $errorPatterns = [
            'remove',
            'delete',
            'failed',
            'error',
            'violation',
            'denied',
            'invalid',
            'constraint',
            'exception'
        ];
        
        $searchText = strtolower($log['message'] . ' ' . $log['action']);
        
        foreach ($errorPatterns as $pattern) {
            if (strpos($searchText, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get simplified error title
     */
    private function getErrorTitle(array $log): string
    {
        $component = ucfirst($log['component']);
        
        switch ($log['action']) {
            case 'failed_login':
                return 'Échec de connexion';
            case 'payment_failed':
                return 'Paiement échoué';
            case 'security_violation':
                return 'Violation de sécurité';
            case 'constraint_violation':
                return 'Erreur de contrainte DB';
            case 'database_error':
                return 'Erreur base de données';
            case 'validation_error':
                return 'Erreur de validation';
            case 'permission_denied':
                return 'Accès refusé';
            case 'remove':
            case 'delete':
                return "Suppression {$log['entity_type']}";
            default:
                // For other actions, create a meaningful title
                if (strpos(strtolower($log['message']), 'erreur') !== false) {
                    return "Erreur {$component}";
                } elseif (strpos(strtolower($log['message']), 'échec') !== false) {
                    return "Échec {$component}";
                } else {
                    return "Activité {$component}";
                }
        }
    }

    /**
     * Get log distribution for charts
     */
    public function getLogDistribution(): array
    {
        $logs = $this->getFormattedAuditLogs(['limit' => 200]);
        $distribution = ['info' => 0, 'warning' => 0, 'error' => 0, 'debug' => 0];

        foreach ($logs as $log) {
            $distribution[$log['level']]++;
        }

        $total = array_sum($distribution);
        if ($total === 0) {
            return ['info' => 100, 'warning' => 0, 'error' => 0, 'debug' => 0];
        }

        return [
            'info' => round(($distribution['info'] / $total) * 100),
            'warning' => round(($distribution['warning'] / $total) * 100),
            'error' => round(($distribution['error'] / $total) * 100),
            'debug' => round(($distribution['debug'] / $total) * 100),
        ];
    }

    /**
     * Get system health metrics (simulated for now)
     */
    public function getSystemHealth(): array
    {
        return [
            'cpu' => rand(30, 70),
            'memory' => rand(60, 85),
            'disk' => rand(25, 50),
            'network' => rand(10, 25),
            'active_users' => rand(15, 50),
            'system_load' => round(rand(10, 300) / 100, 2)
        ];
    }

    /**
     * Export logs to various formats
     */
    public function exportLogs(array $filters, string $format = 'csv'): array|string
    {
        $logs = $this->getFormattedAuditLogs($filters);
        
        switch ($format) {
            case 'json':
                return $logs;
            case 'csv':
                return $this->formatLogsAsCsv($logs);
            case 'txt':
                return $this->formatLogsAsText($logs);
            default:
                return $logs;
        }
    }

    /**
     * Format logs as CSV
     */
    private function formatLogsAsCsv(array $logs): array
    {
        $csv = [];
        $csv[] = ['Time', 'Level', 'Component', 'Message', 'User', 'Entity Type'];
        
        foreach ($logs as $log) {
            $csv[] = [
                $log['time_full'],
                strtoupper($log['level']),
                $log['component'],
                $log['message'],
                $log['user_name'],
                $log['entity_type']
            ];
        }
        
        return $csv;
    }

    /**
     * Format logs as plain text
     */
    private function formatLogsAsText(array $logs): string
    {
        $text = "JoodKitchen System Logs Export\n";
        $text .= "Generated: " . (new \DateTime())->format('Y-m-d H:i:s') . "\n";
        $text .= str_repeat("=", 50) . "\n\n";
        
        foreach ($logs as $log) {
            $text .= sprintf(
                "[%s] %s [%s] %s\n",
                $log['time_full'],
                strtoupper($log['level']),
                $log['component'],
                $log['message']
            );
        }
        
        return $text;
    }
} 