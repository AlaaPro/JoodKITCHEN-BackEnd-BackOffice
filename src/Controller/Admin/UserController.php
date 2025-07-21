<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Entity\ClientProfile;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class UserController extends AbstractController
{
    #[Route('/api/clients', name: 'api_clients_list', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getClients(
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        try {
            $page = $request->query->getInt('page', 1);
            $perPage = $request->query->getInt('perPage', 10);
            $search = $request->query->get('search');
            $status = $request->query->get('status');
            $date = $request->query->get('date');
            $zone = $request->query->get('zone');

            $qb = $entityManager->createQueryBuilder()
                ->select('u', 'cp', 'c', 'a')
                ->from(User::class, 'u')
                ->leftJoin('u.clientProfile', 'cp')
                ->leftJoin('u.commandes', 'c')
                ->leftJoin('u.abonnements', 'a')
                ->where('cp.id IS NOT NULL');

            // Get sort parameters
            $sortField = $request->query->get('sort', 'id');
            $sortOrder = $request->query->get('order', 'ASC');

            // Validate sort field
            $allowedSortFields = ['id', 'nom', 'prenom', 'email', 'createdAt'];
            if (in_array($sortField, $allowedSortFields)) {
                $qb->orderBy('u.' . $sortField, $sortOrder);
            } else {
                $qb->orderBy('u.id', 'ASC');  // Default sorting
            }

            // Apply filters
            if ($search) {
                $qb->andWhere('u.nom LIKE :search OR u.prenom LIKE :search OR u.email LIKE :search OR u.telephone LIKE :search')
                   ->setParameter('search', '%' . $search . '%');
            }

            if ($status) {
                $qb->andWhere('u.isActive = :status')
                   ->setParameter('status', $status === 'active');
            }

            if ($date) {
                $qb->andWhere('u.createdAt >= :date')
                   ->setParameter('date', new \DateTime($date));
            }

            if ($zone) {
                $qb->andWhere('u.ville = :zone')
                   ->setParameter('zone', $zone);
            }

            // Execute the query to get all users
            $users = $qb->getQuery()->getResult();
            $total = count($users);

            // Prepare response data
            $clientsData = [];
            foreach ($users as $user) {
                $profile = $user->getClientProfile();
                $lastOrder = $user->getCommandes()->last();
                
                // Calculate total spent
                $totalSpent = array_reduce(
                    $user->getCommandes()->toArray(),
                    fn($sum, $order) => $sum + ($order->getTotal() ?? 0),
                    0
                );

                $clientsData[] = [
                    'id' => $user->getId(),
                    'nom' => $user->getNom(),
                    'prenom' => $user->getPrenom(),
                    'email' => $user->getEmail(),
                    'telephone' => $user->getTelephone(),
                    'is_active' => $user->getIsActive(),
                    'created_at' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
                    'total_orders' => count($user->getCommandes()),
                    'total_spent' => $totalSpent,
                    'last_order' => $lastOrder ? [
                        'id' => $lastOrder->getId(),
                        'date' => $lastOrder->getDateCommande()->format('Y-m-d H:i:s'),
                        'total' => $lastOrder->getTotal()
                    ] : null,
                    'client_profile' => $profile ? [
                        'id' => $profile->getId(),
                        'points_fidelite' => $profile->getPointsFidelite(),
                        'adresse_livraison' => $profile->getAdresseLivraison()
                    ] : null
                ];
            }

            // Calculate statistics
            $activeCount = count(array_filter($users, fn($u) => $u->getIsActive()));
            $new30DaysCount = count(array_filter($users, function($u) {
                return $u->getCreatedAt() > new \DateTime('-30 days');
            }));
            $totalSpentAll = array_sum(array_column($clientsData, 'total_spent'));
            $averageOrder = $total > 0 ? $totalSpentAll / $total : 0;

            return new JsonResponse([
                'success' => true,
                'data' => $clientsData,
                'stats' => [
                    'total' => $total,
                    'active' => $activeCount,
                    'new_30_days' => $new30DaysCount,
                    'average_order' => $averageOrder
                ],
                'pagination' => [
                    'page' => $page,
                    'perPage' => $perPage,
                    'total' => $total,
                    'pages' => ceil($total / $perPage)
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors du chargement des clients',
                'message' => $this->getParameter('kernel.environment') === 'dev' ? $e->getMessage() : 'Une erreur est survenue',
            ], 500);
        }
    }

    #[Route('/api/clients/{id}', name: 'api_client_details', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getClientDetails(
        int $id,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        try {
            $user = $entityManager->getRepository(User::class)->find($id);
            
            if (!$user || !$user->getClientProfile()) {
                return new JsonResponse([
                    'error' => 'Client non trouvé',
                    'message' => 'Le client demandé n\'existe pas.'
                ], 404);
            }

            $profile = $user->getClientProfile();
            
            return new JsonResponse([
                'success' => true,
                'data' => [
                    'id' => $user->getId(),
                    'nom' => $user->getNom(),
                    'prenom' => $user->getPrenom(),
                    'email' => $user->getEmail(),
                    'telephone' => $user->getTelephone(),
                    'is_active' => $user->getIsActive(),
                    'created_at' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
                    'ville' => $user->getVille(),
                    'adresse' => $user->getAdresse(),
                    'genre' => $user->getGenre(),
                    'date_naissance' => $user->getDateNaissance()?->format('Y-m-d'),
                    'client_profile' => [
                        'id' => $profile->getId(),
                        'points_fidelite' => $profile->getPointsFidelite(),
                        'adresse_livraison' => $profile->getAdresseLivraison()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors du chargement des détails',
                'message' => $this->getParameter('kernel.environment') === 'dev' ? $e->getMessage() : 'Une erreur est survenue',
            ], 500);
        }
    }

    #[Route('/api/clients/{id}/history', name: 'api_client_history', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getClientHistory(
        int $id,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        try {
            $user = $entityManager->getRepository(User::class)->find($id);
            
            if (!$user || !$user->getClientProfile()) {
                return new JsonResponse([
                    'error' => 'Client non trouvé',
                    'message' => 'Le client demandé n\'existe pas.'
                ], 404);
            }

            $orders = $user->getCommandes();
            $abonnements = $user->getAbonnements();
            $fideliteHistory = $user->getClientProfile()->getFidelitePointHistories();

            return new JsonResponse([
                'success' => true,
                'data' => [
                    'orders' => array_map(fn($order) => [
                        'id' => $order->getId(),
                        'date' => $order->getDateCommande()->format('Y-m-d H:i:s'),
                        'total' => $order->getTotal(),
                        'statut' => $order->getStatut()
                    ], $orders->toArray()),
                    'abonnements' => array_map(fn($abo) => [
                        'id' => $abo->getId(),
                        'date_debut' => $abo->getDateDebut()->format('Y-m-d'),
                        'date_fin' => $abo->getDateFin()->format('Y-m-d'),
                        'statut' => $abo->getStatut()
                    ], $abonnements->toArray()),
                    'fidelite_history' => array_map(fn($hist) => [
                        'id' => $hist->getId(),
                        'points' => $hist->getPoints(),
                        'type' => $hist->getType(),
                        'date' => $hist->getCreatedAt()->format('Y-m-d H:i:s')
                    ], $fideliteHistory->toArray())
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors du chargement de l\'historique',
                'message' => $this->getParameter('kernel.environment') === 'dev' ? $e->getMessage() : 'Une erreur est survenue',
            ], 500);
        }
    }
} 