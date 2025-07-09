<?php

namespace App\Service;

use App\Entity\Commande;
use App\Entity\User;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;

class OrderTrackingService
{
    public function __construct(
        private HubInterface $hub,
        private SerializerInterface $serializer,
        private ?LoggerInterface $logger = null
    ) {}

    /**
     * Publish order status update to real-time subscribers
     */
    public function publishOrderUpdate(Commande $commande, string $event = 'order.updated'): void
    {
        try {
            $user = $commande->getUser();
            if (!$user) {
                // If no user is associated with the order, skip real-time updates
                return;
            }

            $data = [
                'id' => $commande->getId(),
                'statut' => $commande->getStatut(),
                'total' => $commande->getTotal(),
                'dateCommande' => $commande->getDateCommande()?->format('Y-m-d H:i:s'),
                'updatedAt' => $commande->getUpdatedAt()?->format('Y-m-d H:i:s'),
                'event' => $event,
                'user' => [
                    'id' => $user->getId(),
                    'nom' => $user->getNom(),
                    'prenom' => $user->getPrenom()
                ]
            ];

            // Create update for specific user (private channel)
            $userUpdate = new Update(
                "order/user/{$user->getId()}",
                json_encode($data)
            );

            // Create update for kitchen staff (public channel for staff)
            $kitchenUpdate = new Update(
                "order/kitchen",
                json_encode($data)
            );

            // Create update for admins
            $adminUpdate = new Update(
                "order/admin", 
                json_encode($data)
            );

            // Publish updates
            $this->hub->publish($userUpdate);
            $this->hub->publish($kitchenUpdate);
            $this->hub->publish($adminUpdate);
        } catch (\Exception $e) {
            // Log the error but don't throw it to avoid breaking the main functionality
            if ($this->logger) {
                $this->logger->warning('Failed to publish Mercure order update: ' . $e->getMessage());
            }
        }
    }

    /**
     * Publish notification to user
     */
    public function publishNotification(?User $user, string $message, string $type = 'info'): void
    {
        if (!$user) {
            // Skip notification if no user provided
            return;
        }

        try {
            $data = [
                'message' => $message,
                'type' => $type,
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
                'userId' => $user->getId()
            ];

            $update = new Update(
                "notification/user/{$user->getId()}",
                json_encode($data)
            );

            $this->hub->publish($update);
        } catch (\Exception $e) {
            // Log the error but don't throw it to avoid breaking the main functionality
            if ($this->logger) {
                $this->logger->warning('Failed to publish Mercure notification: ' . $e->getMessage());
            }
        }
    }

    /**
     * Publish general kitchen updates
     */
    public function publishKitchenUpdate(string $message, array $data = []): void
    {
        try {
            $updateData = [
                'message' => $message,
                'data' => $data,
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
            ];

            $update = new Update(
                "kitchen/updates",
                json_encode($updateData)
            );

            $this->hub->publish($update);
        } catch (\Exception $e) {
            // Log the error but don't throw it to avoid breaking the main functionality
            if ($this->logger) {
                $this->logger->warning('Failed to publish Mercure kitchen update: ' . $e->getMessage());
            }
        }
    }

    /**
     * Get Mercure authorization for user
     */
    public function getMercureAuthorization(User $user): array
    {
        $topics = [
            "order/user/{$user->getId()}",
            "notification/user/{$user->getId()}"
        ];

        // Add role-specific topics
        $roles = $user->getRoles();
        if (in_array('ROLE_KITCHEN', $roles) || in_array('ROLE_ADMIN', $roles)) {
            $topics[] = "order/kitchen";
            $topics[] = "kitchen/updates";
        }

        if (in_array('ROLE_ADMIN', $roles)) {
            $topics[] = "order/admin";
        }

        return [
            'topics' => $topics,
            'hub_url' => $_ENV['MERCURE_PUBLIC_URL'] ?? 'https://localhost/.well-known/mercure'
        ];
    }
} 