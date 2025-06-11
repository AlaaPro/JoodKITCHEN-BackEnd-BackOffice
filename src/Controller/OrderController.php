<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\User;
use App\Service\OrderTrackingService;
use App\Repository\CommandeRepository;
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
        private OrderTrackingService $orderTrackingService
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
    #[IsGranted('ROLE_KITCHEN')]
    public function updateOrderStatus(int $id, Request $request, CommandeRepository $commandeRepository): JsonResponse
    {
        $commande = $commandeRepository->find($id);
        
        if (!$commande) {
            return new JsonResponse(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['statut'])) {
            return new JsonResponse(['error' => 'Status is required'], Response::HTTP_BAD_REQUEST);
        }

        $validStatuses = ['en_attente', 'en_preparation', 'pret', 'en_livraison', 'livre', 'annule'];
        if (!in_array($data['statut'], $validStatuses)) {
            return new JsonResponse(['error' => 'Invalid status'], Response::HTTP_BAD_REQUEST);
        }

        $oldStatus = $commande->getStatut();
        $commande->setStatut($data['statut']);
        
        $this->entityManager->flush();

        // Send real-time update
        $this->orderTrackingService->publishOrderUpdate($commande, 'order.status_changed');
        
        // Send notification to user
        $statusMessages = [
            'en_preparation' => 'Votre commande est en préparation',
            'pret' => 'Votre commande est prête',
            'en_livraison' => 'Votre commande est en cours de livraison',
            'livre' => 'Votre commande a été livrée',
            'annule' => 'Votre commande a été annulée'
        ];

        if (isset($statusMessages[$data['statut']])) {
            $this->orderTrackingService->publishNotification(
                $commande->getUser(),
                $statusMessages[$data['statut']],
                $data['statut'] === 'annule' ? 'warning' : 'success'
            );
        }

        return new JsonResponse([
            'message' => 'Order status updated successfully',
            'order' => [
                'id' => $commande->getId(),
                'old_status' => $oldStatus,
                'new_status' => $commande->getStatut(),
                'updated_at' => $commande->getUpdatedAt()?->format('Y-m-d H:i:s')
            ]
        ]);
    }

    #[Route('/kitchen/dashboard', name: 'api_kitchen_dashboard', methods: ['GET'])]
    #[IsGranted('ROLE_KITCHEN')]
    public function getKitchenDashboard(CommandeRepository $commandeRepository): JsonResponse
    {
        // Get orders that need attention
        $pendingOrders = $commandeRepository->findBy(['statut' => 'en_attente'], ['dateCommande' => 'ASC']);
        $preparingOrders = $commandeRepository->findBy(['statut' => 'en_preparation'], ['dateCommande' => 'ASC']);

        $dashboard = [
            'pending_orders' => array_map([$this, 'formatOrderForDashboard'], $pendingOrders),
            'preparing_orders' => array_map([$this, 'formatOrderForDashboard'], $preparingOrders),
            'statistics' => [
                'total_pending' => count($pendingOrders),
                'total_preparing' => count($preparingOrders),
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

        // Send real-time update with preparation estimate
        $this->orderTrackingService->publishNotification(
            $commande->getUser(),
            "Temps de préparation estimé: {$data['estimated_minutes']} minutes",
            'info'
        );

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
        return [
            'id' => $commande->getId(),
            'user' => [
                'nom' => $commande->getUser()->getNom(),
                'prenom' => $commande->getUser()->getPrenom()
            ],
            'statut' => $commande->getStatut(),
            'total' => $commande->getTotal(),
            'date_commande' => $commande->getDateCommande()?->format('Y-m-d H:i:s'),
            'articles_count' => $commande->getCommandeArticles()->count(),
            'elapsed_time' => $this->calculateElapsedTime($commande->getDateCommande())
        ];
    }

    private function calculateElapsedTime(?\DateTimeInterface $dateCommande): int
    {
        if (!$dateCommande) {
            return 0;
        }
        
        $now = new \DateTime();
        $interval = $now->diff($dateCommande);
        
        return ($interval->h * 60) + $interval->i;
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