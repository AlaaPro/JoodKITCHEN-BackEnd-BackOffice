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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/clients')]
class ClientController extends AbstractController
{
    // ============================================
    // ADMIN ENDPOINTS (existing code)
    // ============================================
    
    #[Route('', name: 'api_clients_list', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
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
    #[IsGranted('ROLE_ADMIN')]
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
    #[IsGranted('ROLE_ADMIN')]
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
    #[IsGranted('ROLE_ADMIN')]
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

    // ============================================
    // CLIENT SELF-MANAGEMENT ENDPOINTS (NEW)
    // ============================================

    #[Route('/profile/me', name: 'api_client_my_profile', methods: ['GET'])]
    #[IsGranted('ROLE_CLIENT')]
    public function getMyProfile(): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();
            
            if (!$user instanceof User) {
                return new JsonResponse([
                    'error' => 'Utilisateur non authentifié',
                    'message' => 'Vous devez être connecté pour accéder à votre profil.',
                    'type' => 'authentication_error'
                ], 401);
            }

            $clientProfile = $user->getClientProfile();
            
            if (!$clientProfile) {
                return new JsonResponse([
                    'error' => 'Profil client non trouvé',
                    'message' => 'Votre profil client n\'existe pas.',
                    'type' => 'profile_not_found'
                ], 404);
            }

            return new JsonResponse([
                'success' => true,
                'data' => [
                    'id' => $user->getId(),
                    'nom' => $user->getNom(),
                    'prenom' => $user->getPrenom(),
                    'email' => $user->getEmail(),
                    'telephone' => $user->getTelephone(),
                    'ville' => $user->getVille(),
                    'adresse' => $user->getAdresse(),
                    'genre' => $user->getGenre(),
                    'date_naissance' => $user->getDateNaissance()?->format('Y-m-d'),
                    'is_active' => $user->getIsActive(),
                    'email_verified' => $user->isEmailVerified(),
                    'last_connexion' => $user->getLastConnexion()?->format('Y-m-d H:i:s'),
                    'created_at' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
                    'updated_at' => $user->getUpdatedAt()->format('Y-m-d H:i:s'),
                    'client_profile' => [
                        'id' => $clientProfile->getId(),
                        'adresse_livraison' => $clientProfile->getAdresseLivraison(),
                        'points_fidelite' => $clientProfile->getPointsFidelite(),
                        'created_at' => $clientProfile->getCreatedAt()->format('Y-m-d H:i:s'),
                        'updated_at' => $clientProfile->getUpdatedAt()->format('Y-m-d H:i:s')
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors du chargement du profil',
                'message' => 'Une erreur inattendue s\'est produite. Veuillez réessayer.',
                'type' => 'server_error',
                'debug' => $this->getParameter('kernel.environment') === 'dev' ? $e->getMessage() : null
            ], 500);
        }
    }

    #[Route('/profile/update', name: 'api_client_update_profile', methods: ['PUT'])]
    #[IsGranted('ROLE_CLIENT')]
    public function updateMyProfile(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        ValidatorInterface $validator
    ): JsonResponse {
        try {
            /** @var User $user */
            $user = $this->getUser();
            
            if (!$user instanceof User) {
                return new JsonResponse([
                    'error' => 'Utilisateur non authentifié',
                    'message' => 'Vous devez être connecté pour modifier votre profil.',
                    'type' => 'authentication_error'
                ], 401);
            }

            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return new JsonResponse([
                    'error' => 'Données invalides',
                    'message' => 'Les données JSON envoyées ne sont pas valides.',
                    'type' => 'validation_error'
                ], 400);
            }

            // Check email uniqueness if email is being changed
            if (isset($data['email']) && $data['email'] !== $user->getEmail()) {
                $existingUser = $entityManager->getRepository(User::class)
                    ->findOneBy(['email' => strtolower(trim($data['email']))]);
                if ($existingUser) {
                    return new JsonResponse([
                        'error' => 'Email déjà utilisé',
                        'message' => 'Cette adresse email est déjà utilisée par un autre compte.',
                        'type' => 'duplicate_email'
                    ], 409);
                }
            }

            // Update user basic information
            if (isset($data['nom'])) {
                $user->setNom(trim($data['nom']));
            }
            if (isset($data['prenom'])) {
                $user->setPrenom(trim($data['prenom']));
            }
            if (isset($data['email'])) {
                $user->setEmail(strtolower(trim($data['email'])));
            }
            if (isset($data['telephone'])) {
                $user->setTelephone($data['telephone']);
            }
            if (isset($data['ville'])) {
                $user->setVille($data['ville']);
            }
            if (isset($data['adresse'])) {
                $user->setAdresse($data['adresse']);
            }
            if (isset($data['genre'])) {
                $user->setGenre($data['genre']);
            }
            if (isset($data['date_naissance'])) {
                $user->setDateNaissance(new \DateTime($data['date_naissance']));
            }

            // Update password if provided
            if (!empty($data['password'])) {
                $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
                $user->setPassword($hashedPassword);
            }

            // Validate user data
            $errors = $validator->validate($user);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
                }
                return new JsonResponse([
                    'error' => 'Erreurs de validation',
                    'message' => 'Les données saisies ne sont pas valides.',
                    'details' => $errorMessages,
                    'type' => 'validation_error'
                ], 400);
            }

            // Update or create client profile
            $clientProfile = $user->getClientProfile();
            if (!$clientProfile) {
                $clientProfile = new ClientProfile();
                $clientProfile->setUser($user);
                $entityManager->persist($clientProfile);
            }

            // Update client profile data
            if (isset($data['adresse_livraison'])) {
                $clientProfile->setAdresseLivraison($data['adresse_livraison']);
            }

            // Validate client profile
            $profileErrors = $validator->validate($clientProfile);
            if (count($profileErrors) > 0) {
                $errorMessages = [];
                foreach ($profileErrors as $error) {
                    $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
                }
                return new JsonResponse([
                    'error' => 'Erreurs de validation du profil',
                    'message' => 'Les données du profil ne sont pas valides.',
                    'details' => $errorMessages,
                    'type' => 'validation_error'
                ], 400);
            }

            // Save changes
            $entityManager->persist($user);
            $entityManager->persist($clientProfile);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Profil mis à jour avec succès',
                'type' => 'success',
                'data' => [
                    'id' => $user->getId(),
                    'nom' => $user->getNom(),
                    'prenom' => $user->getPrenom(),
                    'email' => $user->getEmail(),
                    'telephone' => $user->getTelephone(),
                    'ville' => $user->getVille(),
                    'adresse' => $user->getAdresse(),
                    'genre' => $user->getGenre(),
                    'date_naissance' => $user->getDateNaissance()?->format('Y-m-d'),
                    'updated_at' => $user->getUpdatedAt()->format('Y-m-d H:i:s'),
                    'client_profile' => [
                        'id' => $clientProfile->getId(),
                        'adresse_livraison' => $clientProfile->getAdresseLivraison(),
                        'points_fidelite' => $clientProfile->getPointsFidelite(),
                        'updated_at' => $clientProfile->getUpdatedAt()->format('Y-m-d H:i:s')
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de la mise à jour',
                'message' => 'Une erreur inattendue s\'est produite. Veuillez réessayer.',
                'type' => 'server_error',
                'debug' => $this->getParameter('kernel.environment') === 'dev' ? $e->getMessage() : null
            ], 500);
        }
    }

    #[Route('/profile/delete', name: 'api_client_delete_account', methods: ['DELETE'])]
    #[IsGranted('ROLE_CLIENT')]
    public function deleteMyAccount(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        try {
            /** @var User $user */
            $user = $this->getUser();
            
            if (!$user instanceof User) {
                return new JsonResponse([
                    'error' => 'Utilisateur non authentifié',
                    'message' => 'Vous devez être connecté pour supprimer votre compte.',
                    'type' => 'authentication_error'
                ], 401);
            }

            $data = json_decode($request->getContent(), true);
            
            // Require password confirmation for account deletion
            if (!isset($data['password']) || empty($data['password'])) {
                return new JsonResponse([
                    'error' => 'Mot de passe requis',
                    'message' => 'Veuillez confirmer votre mot de passe pour supprimer votre compte.',
                    'type' => 'validation_error'
                ], 400);
            }

            // Verify password
            if (!$passwordHasher->isPasswordValid($user, $data['password'])) {
                return new JsonResponse([
                    'error' => 'Mot de passe incorrect',
                    'message' => 'Le mot de passe fourni est incorrect.',
                    'type' => 'authentication_error'
                ], 401);
            }

            // Check for active orders
            $activeOrders = $entityManager->getRepository('App\Entity\Commande')
                ->createQueryBuilder('c')
                ->where('c.user = :user')
                ->andWhere('c.statut NOT IN (:completedStatuses)')
                ->setParameter('user', $user)
                ->setParameter('completedStatuses', ['completed', 'cancelled'])
                ->getQuery()
                ->getResult();

            if (!empty($activeOrders)) {
                return new JsonResponse([
                    'error' => 'Suppression impossible',
                    'message' => 'Vous avez des commandes en cours. Veuillez attendre qu\'elles soient terminées avant de supprimer votre compte.',
                    'type' => 'active_orders',
                    'active_orders_count' => count($activeOrders)
                ], 400);
            }

            // Instead of hard delete, mark as inactive and anonymize
            $user->setIsActive(false);
            $user->setEmail('deleted_' . $user->getId() . '@joodkitchen.deleted');
            $user->setNom('Compte');
            $user->setPrenom('Supprimé');
            $user->setTelephone(null);
            $user->setAdresse(null);
            $user->setVille(null);
            
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Votre compte a été supprimé avec succès.',
                'type' => 'success'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de la suppression',
                'message' => 'Une erreur inattendue s\'est produite. Veuillez réessayer.',
                'type' => 'server_error',
                'debug' => $this->getParameter('kernel.environment') === 'dev' ? $e->getMessage() : null
            ], 500);
        }
    }

    #[Route('/orders/history', name: 'api_client_my_orders', methods: ['GET'])]
    #[IsGranted('ROLE_CLIENT')]
    public function getMyOrders(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();
            
            if (!$user instanceof User) {
                return new JsonResponse([
                    'error' => 'Utilisateur non authentifié',
                    'message' => 'Vous devez être connecté pour voir vos commandes.',
                    'type' => 'authentication_error'
                ], 401);
            }

            // Pagination
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = min(50, max(5, (int) $request->query->get('limit', 10)));
            $status = $request->query->get('status');

            $qb = $entityManager->createQueryBuilder()
                ->select('c', 'ca')
                ->from('App\Entity\Commande', 'c')
                ->leftJoin('c.commandeArticles', 'ca')
                ->where('c.user = :user')
                ->setParameter('user', $user)
                ->orderBy('c.dateCommande', 'DESC');

            if ($status) {
                $qb->andWhere('c.statut = :status')
                   ->setParameter('status', $status);
            }

            $total = (clone $qb)->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();
            
            $qb->setFirstResult(($page - 1) * $limit)
               ->setMaxResults($limit);

            $orders = $qb->getQuery()->getResult();

            $ordersData = [];
            foreach ($orders as $order) {
                $articles = [];
                foreach ($order->getCommandeArticles() as $article) {
                    $articles[] = [
                        'nom' => $article->getNom(),
                        'quantite' => $article->getQuantite(),
                        'prix_unitaire' => $article->getPrixUnitaire(),
                        'commentaire' => $article->getCommentaire()
                    ];
                }

                $ordersData[] = [
                    'id' => $order->getId(),
                    'numero' => 'CMD-' . str_pad($order->getId(), 3, '0', STR_PAD_LEFT),
                    'date_commande' => $order->getDateCommande()->format('Y-m-d H:i:s'),
                    'type_livraison' => $order->getTypeLivraison(),
                    'adresse_livraison' => $order->getAdresseLivraison(),
                    'total' => $order->getTotal(),
                    'total_avant_reduction' => $order->getTotalAvantReduction(),
                    'statut' => $order->getStatut(),
                    'commentaire' => $order->getCommentaire(),
                    'articles' => $articles,
                    'articles_count' => count($articles)
                ];
            }

            return new JsonResponse([
                'success' => true,
                'data' => $ordersData,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => (int) $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors du chargement des commandes',
                'message' => 'Une erreur inattendue s\'est produite. Veuillez réessayer.',
                'type' => 'server_error',
                'debug' => $this->getParameter('kernel.environment') === 'dev' ? $e->getMessage() : null
            ], 500);
        }
    }

    #[Route('/fidelite/points', name: 'api_client_fidelite_points', methods: ['GET'])]
    #[IsGranted('ROLE_CLIENT')]
    public function getFidelitePoints(): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();
            
            if (!$user instanceof User) {
                return new JsonResponse([
                    'error' => 'Utilisateur non authentifié',
                    'message' => 'Vous devez être connecté pour voir vos points de fidélité.',
                    'type' => 'authentication_error'
                ], 401);
            }

            $clientProfile = $user->getClientProfile();
            
            if (!$clientProfile) {
                return new JsonResponse([
                    'error' => 'Profil client non trouvé',
                    'message' => 'Votre profil client n\'existe pas.',
                    'type' => 'profile_not_found'
                ], 404);
            }

            // Get fidelity history
            $history = [];
            foreach ($clientProfile->getFidelitePointHistories() as $historyItem) {
                $history[] = [
                    'id' => $historyItem->getId(),
                    'points' => $historyItem->getPoints(),
                    'type' => $historyItem->getType(),
                    'description' => $historyItem->getSource(),
                    'commentaire' => $historyItem->getCommentaire(),
                    'date' => $historyItem->getDate()->format('Y-m-d H:i:s')
                ];
            }

            return new JsonResponse([
                'success' => true,
                'data' => [
                    'current_points' => $clientProfile->getPointsFidelite(),
                    'history' => $history,
                    'history_count' => count($history)
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors du chargement des points',
                'message' => 'Une erreur inattendue s\'est produite. Veuillez réessayer.',
                'type' => 'server_error',
                'debug' => $this->getParameter('kernel.environment') === 'dev' ? $e->getMessage() : null
            ], 500);
        }
    }
} 