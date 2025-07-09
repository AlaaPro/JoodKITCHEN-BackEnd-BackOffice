<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Commande;
use App\Entity\CommandeArticle;
use App\Entity\Plat;
use App\Entity\Menu;
use App\Entity\Payment;
use App\Entity\ClientProfile;
use App\Repository\PlatRepository;
use App\Repository\MenuRepository;
use App\Repository\CategoryRepository;
use App\Repository\UserRepository;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Enum\OrderStatus;

class PosController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private PlatRepository $platRepository,
        private MenuRepository $menuRepository,
        private CategoryRepository $categoryRepository,
        private UserRepository $userRepository,
        private CommandeRepository $commandeRepository,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    // ========================
    // POS INTERFACE ROUTES
    // ========================

    /**
     * Main POS interface
     */
    #[Route('/admin/pos', name: 'admin_pos', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(): Response
    {
        // Map OrderStatus cases to array of value/label pairs
        $orderStatuses = array_map(function($status) {
            return [
                'value' => $status->value,
                'label' => $status->getLabel()
            ];
        }, OrderStatus::cases());

        return $this->render('admin/pos/index.html.twig', [
            'order_statuses' => $orderStatuses
        ]);
    }

    // ========================
    // POS API ENDPOINTS
    // ========================

    /**
     * Get menu organized by categories for POS display
     */
    #[Route('/api/pos/menu/categories', name: 'api_pos_menu_categories', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getMenuCategories(Request $request): JsonResponse
    {
        try {
            $type = $request->query->get('type', 'all'); // all, daily, normal
            $date = $request->query->get('date', date('Y-m-d'));
            
            $categories = [];
            
            // Add daily menus category
            if ($type === 'all' || $type === 'daily') {
                $dailyMenus = $this->menuRepository->findTodaysMenus($date);
                
                if (!empty($dailyMenus)) {
                    $categories['daily_menus'] = [
                        'id' => 'daily_menus',
                        'nom' => 'Menus du Jour',
                        'description' => 'Spécialités du jour',
                        'icon' => '📅',
                        'couleur' => '#a9b73e',
                        'items' => []
                    ];
                    
                    foreach ($dailyMenus as $menu) {
                        // Only show active menus
                        if (!$menu->getActif()) continue;
                        
                        $cuisineFlag = match($menu->getTag()) {
                            'marocain' => '🇲🇦',
                            'italien' => '🇮🇹',
                            'international' => '🌍',
                            default => '🍽️'
                        };
                        
                        $categories['daily_menus']['items'][] = [
                            'id' => $menu->getId(),
                            'type' => 'menu',
                            'nom' => $cuisineFlag . ' ' . $menu->getNom(),
                            'description' => $menu->getDescription(),
                            'prix' => $menu->getPrix(),
                            'image' => $menu->getImageUrl() ?? '',
                            'tag' => $menu->getTag(),
                            'available' => true, // Only available items are included
                            'courses' => $this->getMenuCourses($menu)
                        ];
                    }
                }
            }
            
            // Add normal menu categories
            if ($type === 'all' || $type === 'normal') {
                $dishCategories = $this->categoryRepository->findHierarchicalCategories();
                
                foreach ($dishCategories as $category) {
                    // Only get available dishes
                    $dishes = $this->platRepository->findBy([
                        'category' => $category,
                        'disponible' => true
                    ], ['nom' => 'ASC']);
                    
                    if (!empty($dishes)) {
                        $categoryKey = 'category_' . $category->getId();
                        $categories[$categoryKey] = [
                            'id' => $categoryKey,
                            'nom' => $category->getNom(),
                            'description' => $category->getDescription(),
                            'icon' => $category->getIcon() ?: $this->getCategoryDefaultIcon($category->getNom()),
                            'couleur' => $category->getCouleur() ?: '#666666',
                            'items' => []
                        ];
                        
                        foreach ($dishes as $dish) {
                            $categories[$categoryKey]['items'][] = [
                                'id' => $dish->getId(),
                                'type' => 'plat',
                                'nom' => $dish->getNom(),
                                'description' => $dish->getDescription(),
                                'prix' => $dish->getPrix(),
                                'image' => $dish->getImageName() ? '/uploads/plats/' . $dish->getImageName() : null,
                                'category_id' => $dish->getCategory() ? $dish->getCategory()->getId() : null,
                                'category_name' => $dish->getCategory() ? $dish->getCategory()->getNom() : null,
                                'allergenes' => $dish->getAllergenes(),
                                'vegetarien' => $dish->isVegetarien(),
                                'populaire' => $dish->isPopulaire(),
                                'tempsPreparation' => $dish->getTempsPreparation(),
                                'available' => true // Only available items are included
                            ];
                        }
                    }
                }
                
                // Add normal menus if any
                $normalMenus = $this->menuRepository->findBy([
                    'type' => 'normal',
                    'actif' => true // Only active menus
                ], ['nom' => 'ASC']);
                
                if (!empty($normalMenus)) {
                    $categories['normal_menus'] = [
                        'id' => 'normal_menus',
                        'nom' => 'Menus Normaux',
                        'description' => 'Menus permanents',
                        'icon' => '🍽️',
                        'couleur' => '#da3c33',
                        'items' => []
                    ];
                    
                    foreach ($normalMenus as $menu) {
                        $categories['normal_menus']['items'][] = [
                            'id' => $menu->getId(),
                            'type' => 'menu',
                            'nom' => $menu->getNom(),
                            'description' => $menu->getDescription(),
                            'prix' => $menu->getPrix(),
                            'image' => $menu->getImageUrl(),
                            'tag' => $menu->getTag(),
                            'available' => true, // Only available items are included
                            'courses' => $this->getMenuCourses($menu)
                        ];
                    }
                }
            }
            
            return $this->json([
                'success' => true,
                'data' => array_values($categories),
                'stats' => [
                    'total_categories' => count($categories),
                    'total_items' => array_sum(array_map(fn($cat) => count($cat['items']), $categories)),
                    'date' => $date
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du chargement du menu: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get today's daily menus
     */
    #[Route('/api/pos/menu/today', name: 'api_pos_menu_today', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getTodayMenus(): JsonResponse
    {
        try {
            $today = date('Y-m-d');
            $dailyMenus = $this->menuRepository->findTodaysMenus($today);
            
            $menus = [];
            foreach ($dailyMenus as $menu) {
                $cuisineFlag = match($menu->getTag()) {
                    'marocain' => '🇲🇦',
                    'italien' => '🇮🇹', 
                    'international' => '🌍',
                    default => '🍽️'
                };
                
                $menus[] = [
                    'id' => $menu->getId(),
                    'nom' => $menu->getNom(),
                    'description' => $menu->getDescription(),
                    'prix' => $menu->getPrix(),
                    'tag' => $menu->getTag(),
                    'cuisine_flag' => $cuisineFlag,
                    'image' => $menu->getImageUrl(),
                    'courses' => $this->getMenuCourses($menu),
                    'available' => $menu->getActif()
                ];
            }
            
            return $this->json([
                'success' => true,
                'data' => $menus,
                'date' => $today,
                'coverage' => [
                    'total' => 3,
                    'available' => count($menus),
                    'missing_cuisines' => $this->getMissingCuisines($menus)
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des menus du jour: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search customers for POS
     */
    #[Route('/api/pos/customers/search', name: 'api_pos_customers_search', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function searchCustomers(Request $request): JsonResponse
    {
        try {
            $query = $request->query->get('q', '');
            $limit = min($request->query->getInt('limit', 10), 50);
            
            if (strlen($query) < 2) {
                return $this->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'Veuillez saisir au moins 2 caractères'
                ]);
            }
            
            $customers = $this->userRepository->searchCustomers($query, $limit);
            
            $results = [];
            foreach ($customers as $customer) {
                $profile = $customer->getClientProfile();
                $results[] = [
                    'id' => $customer->getId(),
                    'nom' => $customer->getNom(),
                    'prenom' => $customer->getPrenom(),
                    'email' => $customer->getEmail(),
                    'telephone' => $customer->getTelephone(),
                    'full_name' => $customer->getPrenom() . ' ' . $customer->getNom(),
                    'loyalty_points' => $profile?->getPointsFidelite() ?? 0,
                    'total_orders' => $customer->getCommandes()->count(),
                    'is_vip' => $profile?->getPointsFidelite() > 500
                ];
            }
            
            return $this->json([
                'success' => true,
                'data' => $results,
                'count' => count($results),
                'query' => $query
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la recherche: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create quick customer for POS
     */
    #[Route('/api/pos/customers/quick-create', name: 'api_pos_customers_quick_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function createQuickCustomer(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data || empty($data['nom']) || empty($data['prenom'])) {
                return $this->json([
                    'success' => false,
                    'message' => 'Nom et prénom requis'
                ], 400);
            }
            
            // Check if email exists if provided
            if (!empty($data['email'])) {
                $existingUser = $this->userRepository->findOneBy(['email' => $data['email']]);
                if ($existingUser) {
                    return $this->json([
                        'success' => false,
                        'message' => 'Un client avec cet email existe déjà'
                    ], 409);
                }
            }
            
            // Create new customer
            $customer = new User();
            $customer->setNom(trim($data['nom']));
            $customer->setPrenom(trim($data['prenom']));
            $customer->setEmail($data['email'] ?? null);
            $customer->setTelephone($data['telephone'] ?? null);
            $customer->setRoles(['ROLE_CLIENT']);
            $customer->setIsActive(true);
            
            // Set a default password if email is provided
            if (!empty($data['email'])) {
                $defaultPassword = 'JoodKitchen123!';
                $hashedPassword = $this->passwordHasher->hashPassword($customer, $defaultPassword);
                $customer->setPassword($hashedPassword);
            }
            
            // Create client profile
            $clientProfile = new ClientProfile();
            $clientProfile->setUser($customer);
            $clientProfile->setPointsFidelite(0);
            
            $this->entityManager->persist($customer);
            $this->entityManager->persist($clientProfile);
            $this->entityManager->flush();
            
            return $this->json([
                'success' => true,
                'message' => 'Client créé avec succès',
                'data' => [
                    'id' => $customer->getId(),
                    'nom' => $customer->getNom(),
                    'prenom' => $customer->getPrenom(),
                    'full_name' => $customer->getPrenom() . ' ' . $customer->getNom(),
                    'email' => $customer->getEmail(),
                    'telephone' => $customer->getTelephone(),
                    'loyalty_points' => 0,
                    'is_new' => true
                ]
            ], 201);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la création du client: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create POS order
     */
    #[Route('/api/pos/orders', name: 'api_pos_orders_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function createOrder(Request $request): JsonResponse
    {
        $this->entityManager->beginTransaction();
        
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data || empty($data['items'])) {
                throw new \Exception('Données de commande invalides');
            }
            
            // Create customer if provided
            $customer = null;
            if (!empty($data['customer']['email'])) {
                $customer = $this->userRepository->findOneBy(['email' => $data['customer']['email']]);
            }
            
            // Create new order
            $order = new Commande();
            if ($customer) {
                $order->setUser($customer);
            }
            $order->setStatut(OrderStatus::PENDING->value);
            $order->setTypeLivraison($data['type_livraison'] ?? 'sur_place');
            
            // Add table number for dine-in
            if (!empty($data['table_number'])) {
                $order->setCommentaire('Table #' . $data['table_number']);
            }
            
            // Add delivery address if needed
            if (!empty($data['adresse_livraison'])) {
                $order->setAdresseLivraison($data['adresse_livraison']);
            }
            
            $totalAmount = 0;
            
            // Add order items
            foreach ($data['items'] as $itemData) {
                $orderItem = new CommandeArticle();
                $orderItem->setCommande($order);
                $orderItem->setQuantite($itemData['quantite'] ?? 1);
                $orderItem->setCommentaire($itemData['commentaire'] ?? null);
                
                if ($itemData['type'] === 'plat' && !empty($itemData['plat_id'])) {
                    $plat = $this->platRepository->find($itemData['plat_id']);
                    if (!$plat) {
                        throw new \Exception('Plat introuvable: ' . $itemData['plat_id']);
                    }
                    $orderItem->setPlat($plat);
                    $orderItem->setPrixUnitaire($plat->getPrix());
                } elseif ($itemData['type'] === 'menu' && !empty($itemData['menu_id'])) {
                    $menu = $this->menuRepository->find($itemData['menu_id']);
                    if (!$menu) {
                        throw new \Exception('Menu introuvable: ' . $itemData['menu_id']);
                    }
                    $orderItem->setMenu($menu);
                    $orderItem->setPrixUnitaire($menu->getPrix());
                } else {
                    throw new \Exception('Type d\'article invalide: ' . json_encode($itemData));
                }
                
                $totalAmount += (float)$orderItem->getPrixUnitaire() * $orderItem->getQuantite();
                $this->entityManager->persist($orderItem);
            }
            
            // Apply discount if provided
            if (!empty($data['discount'])) {
                $discountAmount = $data['discount']['amount'] ?? 0;
                $totalAmount -= (float)$discountAmount;
            }
            
            $order->setTotal(number_format($totalAmount, 2, '.', ''));
            $order->setTotalAvantReduction(number_format($totalAmount + ($data['discount']['amount'] ?? 0), 2, '.', ''));
            
            $this->entityManager->persist($order);
            
            // Process payment if provided
            if (!empty($data['payment'])) {
                $payment = new Payment();
                $payment->setCommande($order);
                $payment->setMontant($order->getTotal());
                $payment->setMethodePaiement($data['payment']['method'] ?? 'especes');
                $payment->setStatut('valide');
                
                $this->entityManager->persist($payment);
                
                // Update order status to confirmed if payment is successful
                $order->setStatut(OrderStatus::CONFIRMED->value);
            }
            
            $this->entityManager->flush();
            $this->entityManager->commit();
            
            return $this->json([
                'success' => true,
                'message' => 'Commande créée avec succès',
                'data' => [
                    'order_id' => $order->getId(),
                    'order_number' => 'CMD-' . str_pad($order->getId(), 6, '0', STR_PAD_LEFT),
                    'total' => $order->getTotal(),
                    'status' => $order->getStatut(),
                    'customer' => $customer ? [
                        'id' => $customer->getId(),
                        'name' => $customer->getPrenom() . ' ' . $customer->getNom()
                    ] : [
                        'id' => null,
                        'name' => 'Client Anonyme'
                    ],
                    'items_count' => count($data['items']),
                    'created_at' => $order->getCreatedAt()->format('Y-m-d H:i:s')
                ]
            ], 201);
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la commande: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get today's order history for POS
     */
    #[Route('/api/pos/orders/history', name: 'api_pos_orders_history', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getOrderHistory(Request $request): JsonResponse
    {
        try {
            $date = $request->query->get('date', date('Y-m-d'));
            $search = $request->query->get('search', '');
            $status = $request->query->get('status', 'all');
            
            $dateStart = new \DateTime($date . ' 00:00:00');
            $dateEnd = new \DateTime($date . ' 23:59:59');
            
            // Get today's orders
            $orders = $this->commandeRepository->findOrdersByDateRange($dateStart, $dateEnd);
            
            // Filter by status if specified
            if ($status !== 'all' && !empty($status)) {
                $orders = array_filter($orders, fn($order) => $order->getStatut() === $status);
            }
            
            // Filter by search term if provided
            if (!empty($search)) {
                $orders = array_filter($orders, function($order) use ($search) {
                    $searchLower = strtolower($search);
                    $orderNumber = 'CMD-' . str_pad($order->getId(), 6, '0', STR_PAD_LEFT);
                    
                    // Handle anonymous orders
                    if ($order->getUser()) {
                        $customerName = strtolower($order->getUser()->getPrenom() . ' ' . $order->getUser()->getNom());
                        $customerEmail = strtolower($order->getUser()->getEmail() ?? '');
                    } else {
                        $customerName = 'client anonyme';
                        $customerEmail = '';
                    }
                    
                    return str_contains(strtolower($orderNumber), $searchLower) ||
                           str_contains($customerName, $searchLower) ||
                           str_contains($customerEmail, $searchLower);
                });
            }
            
            // Format orders for display
            $formattedOrders = [];
            foreach ($orders as $order) {
                $customer = $order->getUser();
                $items = [];
                
                foreach ($order->getCommandeArticles() as $article) {
                    if ($article->getPlat()) {
                        $items[] = [
                            'nom' => $article->getPlat()->getNom(),
                            'quantite' => $article->getQuantite(),
                            'prix' => $article->getPrixUnitaire(),
                            'type' => 'plat'
                        ];
                    } elseif ($article->getMenu()) {
                        $items[] = [
                            'nom' => $article->getMenu()->getNom(),
                            'quantite' => $article->getQuantite(),
                            'prix' => $article->getPrixUnitaire(),
                            'type' => 'menu'
                        ];
                    }
                }
                
                $formattedOrders[] = [
                    'id' => $order->getId(),
                    'order_number' => 'CMD-' . str_pad($order->getId(), 6, '0', STR_PAD_LEFT),
                    'customer' => $customer ? [
                        'id' => $customer->getId(),
                        'name' => $customer->getPrenom() . ' ' . $customer->getNom(),
                        'email' => $customer->getEmail(),
                        'telephone' => $customer->getTelephone()
                    ] : [
                        'id' => null,
                        'name' => 'Client Anonyme',
                        'email' => '',
                        'telephone' => ''
                    ],
                    'items' => $items,
                    'items_count' => count($items),
                    'total' => $order->getTotal(),
                    'status' => $order->getStatut(),
                    'status_label' => $this->getStatusLabel($order->getStatut()),
                    'type' => $order->getTypeLivraison() ?? 'sur_place',
                    'type_label' => $this->getTypeLabel($order->getTypeLivraison() ?? 'sur_place'),
                    'created_at' => $order->getCreatedAt()->format('H:i:s'),
                    'date_commande' => $order->getDateCommande()->format('H:i:s'),
                    'commentaire' => $order->getCommentaire()
                ];
            }
            
            // Sort by most recent first
            usort($formattedOrders, fn($a, $b) => $b['id'] <=> $a['id']);
            
            return $this->json([
                'success' => true,
                'data' => $formattedOrders,
                'count' => count($formattedOrders),
                'date' => $date,
                'filters' => [
                    'search' => $search,
                    'status' => $status
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du chargement de l\'historique: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get categories for filtering
     */
    #[Route('/api/pos/categories', name: 'api_pos_categories', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getCategories(): JsonResponse
    {
        try {
            $categories = $this->categoryRepository->findBy([], ['nom' => 'ASC']);
            
            $result = [];
            foreach ($categories as $category) {
                $result[] = [
                    'id' => $category->getId(),
                    'nom' => $category->getNom(),
                    'description' => $category->getDescription(),
                    'icon' => $category->getIcon(),
                    'couleur' => $category->getCouleur()
                ];
            }
            
            return $this->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des catégories: ' . $e->getMessage()
            ], 500);
        }
    }

    // ========================
    // PRIVATE HELPER METHODS
    // ========================

    private function getMenuCourses(Menu $menu): array
    {
        $courses = [];
        foreach ($menu->getMenuPlats() as $menuPlat) {
            $plat = $menuPlat->getPlat();
            $courses[] = [
                'ordre' => $menuPlat->getOrdre(),
                'plat' => [
                    'id' => $plat->getId(),
                    'nom' => $plat->getNom(),
                    'description' => $plat->getDescription(),
                    'category' => $plat->getCategorie(),
                    'image' => $plat->getImageName() ? '/uploads/plats/' . $plat->getImageName() : null
                ]
            ];
        }
        
        usort($courses, fn($a, $b) => $a['ordre'] <=> $b['ordre']);
        return $courses;
    }

    private function getMissingCuisines(array $menus): array
    {
        $availableCuisines = array_map(fn($menu) => $menu['tag'], $menus);
        $allCuisines = ['marocain', 'italien', 'international'];
        return array_diff($allCuisines, $availableCuisines);
    }

    private function getCategoryDefaultIcon(string $categoryName): string
    {
        $iconMap = [
            'entrée' => '🥗',
            'entrees' => '🥗',
            'plat principal' => '🍽️',
            'plats principaux' => '🍽️',
            'dessert' => '🍰',
            'desserts' => '🍰',
            'boisson' => '🥤',
            'boissons' => '🥤',
            'pizza' => '🍕',
            'pizzas' => '🍕',
            'sandwich' => '🥪',
            'sandwichs' => '🥪',
            'salade' => '🥗',
            'salades' => '🥗',
            'pasta' => '🍝',
            'pâtes' => '🍝',
            'risotto' => '🍚'
        ];
        
        return $iconMap[strtolower($categoryName)] ?? '🍽️';
    }

    private function getStatusLabel(string $status): string
    {
        // Try to get label from OrderStatus enum first
        foreach (OrderStatus::cases() as $orderStatus) {
            if ($orderStatus->value === $status) {
                return $orderStatus->getLabel();
            }
        }
        
        // Fallback to hardcoded for any status not in enum
        $statusLabels = [
            'en_attente' => 'En attente',
            'confirme' => 'Confirmé',
            'en_preparation' => 'En préparation',
            'pret' => 'Prêt',
            'livre' => 'Livré',
            'annule' => 'Annulé'
        ];
        
        return $statusLabels[$status] ?? ucfirst($status);
    }

    private function getTypeLabel(string $type): string
    {
        $typeLabels = [
            'sur_place' => 'Sur place',
            'a_emporter' => 'À emporter'
        ];
        
        return $typeLabels[$type] ?? ucfirst($type);
    }
} 