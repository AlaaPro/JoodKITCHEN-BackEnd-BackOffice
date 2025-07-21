<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Entity\ClientProfile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/clients')]
#[IsGranted('ROLE_ADMIN')]
class ClientController extends AbstractController
{
    #[Route('', name: 'api_clients_list', methods: ['GET'])]
    public function list(Request $request, EntityManagerInterface $entityManager): JsonResponse 
    {
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

            // Apply sorting
            $sortField = $request->query->get('sort', 'id');
            $sortOrder = $request->query->get('order', 'ASC');
            $allowedSortFields = ['id', 'nom', 'prenom', 'email', 'createdAt'];
            if (in_array($sortField, $allowedSortFields)) {
                $qb->orderBy('u.' . $sortField, $sortOrder);
            } else {
                $qb->orderBy('u.id', 'ASC');
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

            $users = $qb->getQuery()->getResult();
            $total = count($users);

            // Prepare response data
            $clientsData = array_map(function($user) {
                $profile = $user->getClientProfile();
                $lastOrder = $user->getCommandes()->last();
                $totalSpent = array_reduce(
                    $user->getCommandes()->toArray(),
                    fn($sum, $order) => $sum + ($order->getTotal() ?? 0),
                    0
                );

                return [
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
            }, $users);

            // Calculate statistics
            $activeCount = count(array_filter($users, fn($u) => $u->getIsActive()));
            $new30DaysCount = count(array_filter($users, fn($u) => $u->getCreatedAt() > new \DateTime('-30 days')));
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

    #[Route('/{id}', name: 'api_client_details', methods: ['GET'])]
    public function details(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
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

    #[Route('/{id}/history', name: 'api_client_history', methods: ['GET'])]
    public function history(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $user = $entityManager->getRepository(User::class)->find($id);
            
            if (!$user || !$user->getClientProfile()) {
                return new JsonResponse([
                    'error' => 'Client non trouvé',
                    'message' => 'Le client demandé n\'existe pas.'
                ], 404);
            }

            return new JsonResponse([
                'success' => true,
                'data' => [
                    'orders' => array_map(fn($order) => [
                        'id' => $order->getId(),
                        'date' => $order->getDateCommande()->format('Y-m-d H:i:s'),
                        'total' => $order->getTotal(),
                        'statut' => $order->getStatut()
                    ], $user->getCommandes()->toArray()),
                    'abonnements' => array_map(fn($abo) => [
                        'id' => $abo->getId(),
                        'date_debut' => $abo->getDateDebut()->format('Y-m-d'),
                        'date_fin' => $abo->getDateFin()->format('Y-m-d'),
                        'statut' => $abo->getStatut()
                    ], $user->getAbonnements()->toArray()),
                    'fidelite_history' => array_map(fn($hist) => [
                        'id' => $hist->getId(),
                        'points' => $hist->getPoints(),
                        'type' => $hist->getType(),
                        'date' => $hist->getCreatedAt()->format('Y-m-d H:i:s')
                    ], $user->getClientProfile()->getFidelitePointHistories()->toArray())
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors du chargement de l\'historique',
                'message' => $this->getParameter('kernel.environment') === 'dev' ? $e->getMessage() : 'Une erreur est survenue',
            ], 500);
        }
    }

    #[Route('/{id}/toggle-status', name: 'api_client_toggle_status', methods: ['POST'])]
    public function toggleStatus(int $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $user = $entityManager->getRepository(User::class)->find($id);
            
            if (!$user || !$user->getClientProfile()) {
                return new JsonResponse([
                    'error' => 'Client non trouvé',
                    'message' => 'Le client demandé n\'existe pas.'
                ], 404);
            }

            $data = json_decode($request->getContent(), true);
            $isActive = $data['is_active'] ?? false;

            if (!$isActive && $user->hasActiveOrders()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Impossible de désactiver un client ayant des commandes en cours'
                ], 400);
            }

            $user->setIsActive($isActive);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => $isActive ? 'Client activé avec succès' : 'Client désactivé avec succès'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de la mise à jour du statut',
                'message' => $this->getParameter('kernel.environment') === 'dev' ? $e->getMessage() : 'Une erreur est survenue'
            ], 500);
        }
    }
} 