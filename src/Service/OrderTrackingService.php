<?php

namespace App\Service;

use App\Entity\Commande;
use App\Entity\User;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Serializer\SerializerInterface;

class OrderTrackingService
{
    public function __construct(
        private HubInterface $hub,
        private SerializerInterface $serializer
    ) {}

    /**
     * Publish order status update to real-time subscribers
     */
    public function publishOrderUpdate(Commande $commande, string $event = 'order.updated'): void
    {
        $data = [
            'id' => $commande->getId(),
            'statut' => $commande->getStatut(),
            'total' => $commande->getTotal(),
            'dateCommande' => $commande->getDateCommande()?->format('Y-m-d H:i:s'),
            'updatedAt' => $commande->getUpdatedAt()?->format('Y-m-d H:i:s'),
            'event' => $event,
            'user' => [
                'id' => $commande->getUser()->getId(),
                'nom' => $commande->getUser()->getNom(),
                'prenom' => $commande->getUser()->getPrenom()
            ]
        ];

        // Create update for specific user (private channel)
        $userUpdate = new Update(
            "order/user/{$commande->getUser()->getId()}",
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
    }

    /**
     * Publish notification to user
     */
    public function publishNotification(User $user, string $message, string $type = 'info'): void
    {
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
    }

    /**
     * Publish general kitchen updates
     */
    public function publishKitchenUpdate(string $message, array $data = []): void
    {
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