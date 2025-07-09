<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\User;
use App\Entity\OrderStatusHistory;
use App\Enum\OrderStatus;
use App\Service\OrderTrackingService;
use App\Repository\CommandeRepository;
use App\Repository\OrderStatusHistoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/orders')]
class OrderController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OrderTrackingService $orderTrackingService,
        private OrderStatusHistoryRepository $statusHistoryRepository
    ) {}

    #[Route('/tracking/subscribe', name: 'api_order_tracking_subscribe', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getTrackingSubscription(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $authorization = $this->orderTrackingService->getMercureAuthorization($user);

        return new JsonResponse([
            'mercure' => $authorization,
            'instructions' => [
                'connect_to' => $authorization['hub_url'],
                'subscribe_to_topics' => $authorization['topics'],
                'authorization_header' => 'Bearer ' . $this->generateMercureJWT($user)
            ]
        ]);
    }

    #[Route('/{id}/status', name: 'api_order_update_status', methods: ['PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    public function updateStatus(Commande $commande, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['statut'])) {
            return new JsonResponse(['error' => 'Status is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $newStatus = OrderStatus::from($data['statut']);
        } catch (\ValueError $e) {
            return new JsonResponse(['error' => 'Invalid status'], Response::HTTP_BAD_REQUEST);
        }

        $oldStatus = $commande->getStatusEnum();
        
        // Check if status transition is allowed
        if (!$oldStatus->canTransitionTo($newStatus)) {
            return new JsonResponse([
                'error' => 'Invalid status transition',
                'message' => "Cannot change status from {$oldStatus->getLabel()} to {$newStatus->getLabel()}"
            ], Response::HTTP_BAD_REQUEST);
        }

        // Create status history record BEFORE updating the order
        $statusHistory = new OrderStatusHistory();
        $statusHistory->setCommande($commande);
        $statusHistory->setStatus($newStatus->value);
        $statusHistory->setPreviousStatus($oldStatus->value);
        $statusHistory->setChangedBy($this->getUser());
        $statusHistory->setComment($data['comment'] ?? null);

        $this->entityManager->persist($statusHistory);

        // Update order status
        $commande->setStatut($newStatus->value);
        $this->entityManager->flush();

        // Send real-time update
        $this->orderTrackingService->publishOrderUpdate($commande, 'order.status_changed');
        
        // Send notification to user
        if ($commande->getUser()) {
            $this->orderTrackingService->publishNotification(
                $commande->getUser(),
                $newStatus->getNotificationMessage(),
                $newStatus->getNotificationType()
            );
        }

        return new JsonResponse([
            'message' => 'Order status updated successfully',
            'order' => [
                'id' => $commande->getId(),
                'old_status' => $oldStatus->value,
                'new_status' => $newStatus->value,
                'updated_at' => $commande->getUpdatedAt()?->format('Y-m-d H:i:s')
            ]
        ]);
    }

    #[Route('/kitchen/dashboard', name: 'api_kitchen_dashboard', methods: ['GET'])]
    #[IsGranted('ROLE_KITCHEN')]
    public function getKitchenDashboard(CommandeRepository $commandeRepository): JsonResponse
    {
        // Get orders for kitchen workflow
        $pendingOrders = $commandeRepository->findBy(['statut' => OrderStatus::PENDING->value], ['dateCommande' => 'ASC']);
        $confirmedOrders = $commandeRepository->findBy(['statut' => OrderStatus::CONFIRMED->value], ['dateCommande' => 'ASC']);
        $preparingOrders = $commandeRepository->findBy(['statut' => OrderStatus::PREPARING->value], ['dateCommande' => 'ASC']);
        $readyOrders = $commandeRepository->findBy(['statut' => OrderStatus::READY->value], ['dateCommande' => 'ASC']);

        // Combine pending and confirmed orders for "new orders" column
        $newOrders = array_merge($pendingOrders, $confirmedOrders);
        
        $dashboard = [
            'pending_orders' => array_map([$this, 'formatOrderForKitchen'], $newOrders),
            'preparing_orders' => array_map([$this, 'formatOrderForKitchen'], $preparingOrders),
            'ready_orders' => array_map([$this, 'formatOrderForKitchen'], $readyOrders),
            'statistics' => [
                'total_pending' => count($newOrders),
                'total_preparing' => count($preparingOrders),
                'total_ready' => count($readyOrders),
                'avg_preparation_time' => $this->calculateAveragePreparationTime()
            ]
        ];

        return new JsonResponse($dashboard);
    }

    #[Route('/{id}/estimate', name: 'api_order_update_estimate', methods: ['PATCH'])]
    #[IsGranted('ROLE_KITCHEN')]
    public function updatePreparationEstimate(int $id, Request $request, CommandeRepository $commandeRepository): JsonResponse
    {
        $commande = $commandeRepository->find($id);
        
        if (!$commande) {
            return new JsonResponse(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['estimated_minutes'])) {
            return new JsonResponse(['error' => 'Estimated minutes is required'], Response::HTTP_BAD_REQUEST);
        }

        // Send real-time update with preparation estimate only if user exists
        if ($commande->getUser()) {
            $this->orderTrackingService->publishNotification(
                $commande->getUser(),
                "Temps de préparation estimé: {$data['estimated_minutes']} minutes",
                'info'
            );
        }

        // Update kitchen dashboard
        $this->orderTrackingService->publishKitchenUpdate(
            "Estimation mise à jour pour la commande #{$commande->getId()}",
            [
                'order_id' => $commande->getId(),
                'estimated_minutes' => $data['estimated_minutes']
            ]
        );

        return new JsonResponse([
            'message' => 'Preparation estimate updated',
            'order_id' => $commande->getId(),
            'estimated_minutes' => $data['estimated_minutes']
        ]);
    }

    private function formatOrderForDashboard(Commande $commande): array
    {
        $user = $commande->getUser();
        return [
            'id' => $commande->getId(),
            'user' => [
                'nom' => $user ? $user->getNom() : 'Client',
                'prenom' => $user ? $user->getPrenom() : ''
            ],
            'statut' => $commande->getStatut(),
            'total' => $commande->getTotal(),
            'date_commande' => $commande->getDateCommande()?->format('c'), // ISO 8601 with timezone
            'articles_count' => $commande->getCommandeArticles()->count(),
            'elapsed_time' => $this->calculateElapsedTime($commande->getDateCommande())
        ];
    }

    private function formatOrderForKitchen(Commande $commande): array
    {
        $items = [];
        foreach ($commande->getCommandeArticles() as $article) {
            $itemName = '';
            if ($article->getPlat()) {
                $itemName = $article->getPlat()->getNom();
            } elseif ($article->getMenu()) {
                $itemName = $article->getMenu()->getNom();
            }
            
            $items[] = [
                'nom' => $itemName,
                'quantite' => $article->getQuantite(),
                'status' => 'pending', // Default status
                'commentaire' => $article->getCommentaire()
            ];
        }

        // Use status history to get accurate timing for each order type
        $status = $commande->getStatusEnum();
        $dateForTiming = null;
        
        switch ($status) {
            case \App\Enum\OrderStatus::PENDING:
            case \App\Enum\OrderStatus::CONFIRMED:
                // For new orders: use timestamp when status changed to PENDING/CONFIRMED
                $dateForTiming = $this->statusHistoryRepository->getStatusTimestamp($commande, $status->value);
                // Fallback to dateCommande if no history found
                if (!$dateForTiming) {
                    $dateForTiming = $commande->getDateCommande();
                }
                break;
                
            case \App\Enum\OrderStatus::PREPARING:
                // For orders in progress: use timestamp when status changed to PREPARING
                $dateForTiming = $this->statusHistoryRepository->getStatusTimestamp($commande, OrderStatus::PREPARING->value);
                // Fallback to dateCommande if no history found
                if (!$dateForTiming) {
                    $dateForTiming = $commande->getDateCommande();
                }
                break;
                
            case \App\Enum\OrderStatus::READY:
                // For ready orders: use timestamp when status changed to READY
                $dateForTiming = $this->statusHistoryRepository->getStatusTimestamp($commande, OrderStatus::READY->value);
                // Fallback to dateCommande if no history found
                if (!$dateForTiming) {
                    $dateForTiming = $commande->getDateCommande();
                }
                break;
                
            default:
                $dateForTiming = $commande->getDateCommande();
                break;
        }

        return [
            'id' => $commande->getId(),
            'user' => [
                'nom' => $commande->getUser() ? $commande->getUser()->getNom() : 'Client',
                'prenom' => $commande->getUser() ? $commande->getUser()->getPrenom() : ''
            ],
            'statut' => $commande->getStatut(),
            'total' => $commande->getTotal(),
            'dateCommande' => $dateForTiming?->format('c'), // ISO 8601 with timezone info for JavaScript
            'created_at' => $commande->getCreatedAt()?->format('c'),
            'articles_count' => $commande->getCommandeArticles()->count(),
            'items' => $items,
            'commentaire' => $commande->getCommentaire(),
            'elapsed_time' => $this->calculateElapsedTime($dateForTiming) // Kept for consistency but JS uses dateCommande
        ];
    }

    private function calculateElapsedTime(?\DateTimeInterface $dateCommande): int
    {
        if (!$dateCommande) {
            return 0;
        }
        
        $now = new \DateTime();
        
        // Calculate total seconds difference (JavaScript expects seconds)
        $totalSeconds = $now->getTimestamp() - $dateCommande->getTimestamp();
        
        // Return total seconds for JavaScript formatting
        return (int) $totalSeconds;
    }

    private function calculateAveragePreparationTime(): int
    {
        // This is a simplified calculation - you might want to store preparation times
        return 25; // Default 25 minutes
    }

    private function generateMercureJWT(User $user): string
    {
        // Generate a simple JWT for Mercure authorization
        // In production, use a proper JWT library
        $payload = [
            'mercure' => [
                'subscribe' => [
                    "order/user/{$user->getId()}",
                    "notification/user/{$user->getId()}"
                ]
            ]
        ];

        // Add role-specific subscriptions
        if (in_array('ROLE_KITCHEN', $user->getRoles()) || in_array('ROLE_ADMIN', $user->getRoles())) {
            $payload['mercure']['subscribe'][] = "order/kitchen";
            $payload['mercure']['subscribe'][] = "kitchen/updates";
        }

        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            $payload['mercure']['subscribe'][] = "order/admin";
        }

        return base64_encode(json_encode($payload));
    }
} 