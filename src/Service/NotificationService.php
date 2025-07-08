<?php

namespace App\Service;

use App\Entity\Commande;
use App\Entity\User;
use App\Entity\Notification;
use App\Enum\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class NotificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CacheInterface $cache
    ) {}

    /**
     * Create and store notification for real-time polling
     */
    public function createNotification(User $user, string $message, string $type = 'info', array $data = []): Notification
    {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setMessage($message);
        $notification->setType($type);
        $notification->setTitre($this->generateTitle($type));
        $notification->setMetadonnees($data);
        $notification->setLu(false);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        // Clear user's notification cache
        $this->clearUserNotificationCache($user->getId());

        return $notification;
    }

    /**
     * Send order status notification
     */
    public function sendOrderStatusNotification(Commande $commande, string $event = 'order.updated'): void
    {
        $status = $commande->getStatusEnum();
        $message = $status->getNotificationMessage();
        $type = $status->getNotificationType();

        if ($commande->getUser()) {
            $this->createNotification(
                $commande->getUser(),
                $message,
                $type,
                [
                    'order_id' => $commande->getId(),
                    'event' => $event,
                    'status' => $commande->getStatut()
                ]
            );
        }
    }

    /**
     * Get unread notifications for user (cached for polling)
     */
    public function getUnreadNotifications(int $userId): array
    {
        $cacheKey = "notifications.unread.{$userId}";
        
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($userId) {
            $item->expiresAfter(30); // 30 seconds cache for real-time feel
            
            $notifications = $this->entityManager->getRepository(Notification::class)
                ->findBy(
                    ['user' => $userId, 'lu' => false],
                    ['createdAt' => 'DESC'],
                    20 // Last 20 unread notifications
                );

            return array_map(function($notification) {
                return [
                    'id' => $notification->getId(),
                    'title' => $notification->getTitre(),
                    'message' => $notification->getMessage(),
                    'type' => $notification->getType(),
                    'data' => $notification->getMetadonnees(),
                    'created_at' => $notification->getCreatedAt()->format('Y-m-d H:i:s'),
                    'read' => $notification->getLu()
                ];
            }, $notifications);
        });
    }

    /**
     * Get order status updates for polling (cPanel-compatible)
     */
    public function getOrderStatusUpdates(int $userId, ?\DateTime $since = null): array
    {
        $since = $since ?? new \DateTime('-5 minutes');
        
        $qb = $this->entityManager->createQueryBuilder();
        $orders = $qb->select('c')
            ->from('App\Entity\Commande', 'c')
            ->where('c.user = :userId')
            ->andWhere('c.updatedAt >= :since')
            ->setParameter('userId', $userId)
            ->setParameter('since', $since)
            ->orderBy('c.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(function($order) {
            return [
                'id' => $order->getId(),
                'statut' => $order->getStatut(),
                'total' => $order->getTotal(),
                'updated_at' => $order->getUpdatedAt()->format('Y-m-d H:i:s'),
                'estimated_delivery' => $this->calculateEstimatedDelivery($order)
            ];
        }, $orders);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        $notification = $this->entityManager->getRepository(Notification::class)
            ->findOneBy(['id' => $notificationId, 'user' => $userId]);

        if (!$notification) {
            return false;
        }

        $notification->setLu(true);
        $notification->setDateLecture(new \DateTime());
        $this->entityManager->flush();

        // Clear cache
        $this->clearUserNotificationCache($userId);

        return true;
    }

    /**
     * Mark all notifications as read for user
     */
    public function markAllAsRead(int $userId): int
    {
        $notifications = $this->entityManager->getRepository(Notification::class)
            ->findBy(['user' => $userId, 'lu' => false]);

        $count = 0;
        foreach ($notifications as $notification) {
            $notification->setLu(true);
            $notification->setDateLecture(new \DateTime());
            $count++;
        }

        $this->entityManager->flush();

        // Clear cache
        $this->clearUserNotificationCache($userId);

        return $count;
    }

    /**
     * Get kitchen dashboard updates (polling-based for cPanel)
     */
    public function getKitchenUpdates(?\DateTime $since = null): array
    {
        $since = $since ?? new \DateTime('-2 minutes');
        
        $cacheKey = 'kitchen.updates.' . $since->getTimestamp();
        
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($since) {
            $item->expiresAfter(60); // 1 minute cache
            
            $qb = $this->entityManager->createQueryBuilder();
            
            // New orders
            $newOrders = $qb->select('c')
                ->from('App\Entity\Commande', 'c')
                ->where('c.createdAt >= :since')
                ->setParameter('since', $since)
                ->getQuery()
                ->getResult();

            // Status updates
            $qb = $this->entityManager->createQueryBuilder();
            $updatedOrders = $qb->select('c')
                ->from('App\Entity\Commande', 'c')
                ->where('c.updatedAt >= :since')
                ->andWhere('c.createdAt < :since') // Exclude new orders (already counted)
                ->setParameter('since', $since)
                ->getQuery()
                ->getResult();

            return [
                'new_orders' => array_map([$this, 'formatOrderForKitchen'], $newOrders),
                'updated_orders' => array_map([$this, 'formatOrderForKitchen'], $updatedOrders),
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
            ];
        });
    }

    /**
     * Send bulk notifications (for promotions, etc.)
     */
    public function sendBulkNotification(array $userIds, string $message, string $type = 'info', array $data = []): int
    {
        $count = 0;
        $users = $this->entityManager->getRepository(User::class)->findBy(['id' => $userIds]);
        
        foreach ($users as $user) {
            $this->createNotification($user, $message, $type, $data);
            $count++;
        }
        
        return $count;
    }

    /**
     * Get notification statistics
     */
    public function getNotificationStats(int $userId): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        $total = $qb->select('COUNT(n.id)')
            ->from('App\Entity\Notification', 'n')
            ->where('n.user = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();

        $unread = $qb->select('COUNT(n.id)')
            ->from('App\Entity\Notification', 'n')
            ->where('n.user = :userId')
            ->andWhere('n.lu = false')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => (int)$total,
            'unread' => (int)$unread,
            'read' => (int)$total - (int)$unread
        ];
    }

    private function clearUserNotificationCache(int $userId): void
    {
        $this->cache->delete("notifications.unread.{$userId}");
    }

    private function formatOrderForKitchen(Commande $order): array
    {
        return [
            'id' => $order->getId(),
            'user_name' => $order->getUser()->getNom() . ' ' . $order->getUser()->getPrenom(),
            'statut' => $order->getStatut(),
            'total' => $order->getTotal(),
            'items_count' => $order->getCommandeArticles()->count(),
            'created_at' => $order->getDateCommande()->format('Y-m-d H:i:s'),
            'elapsed_minutes' => $this->calculateElapsedMinutes($order->getDateCommande())
        ];
    }

    private function calculateElapsedMinutes(\DateTime $orderDate): int
    {
        $now = new \DateTime();
        $interval = $now->diff($orderDate);
        return ($interval->h * 60) + $interval->i;
    }

    private function calculateEstimatedDelivery(Commande $order): ?string
    {
        $minutes = $order->getStatusEnum()->getEstimatedDeliveryMinutes();
        
        if ($minutes) {
            return (new \DateTime())->modify("+{$minutes} minutes")->format('H:i');
        }
        
        return null;
    }

    private function generateTitle(string $type): string
    {
        $titles = [
            'info' => 'Information',
            'success' => 'Succès',
            'warning' => 'Attention',
            'error' => 'Erreur',
            'commande' => 'Commande',
            'promotion' => 'Promotion'
        ];

        return $titles[$type] ?? 'Notification';
    }
} 