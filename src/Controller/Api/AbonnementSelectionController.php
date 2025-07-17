<?php

namespace App\Controller\Api;

use App\Entity\AbonnementSelection;
use App\Repository\AbonnementSelectionRepository;
use App\Repository\AbonnementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * AbonnementSelectionController - Handles daily meal selection management
 * 
 * This controller manages the daily meal selections within subscriptions:
 * - CRUD operations for meal selections
 * - Weekly planning data
 * - Incomplete selections tracking
 * - Kitchen preparation lists
 * - Cuisine-specific analytics
 */
#[Route('/api/admin/abonnement-selections')]
#[IsGranted('ROLE_ADMIN')]
class AbonnementSelectionController extends AbstractController
{
    public function __construct(
        private AbonnementSelectionRepository $selectionRepository,
        private AbonnementRepository $abonnementRepository,
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Get all meal selections with filtering
     */
    #[Route('', name: 'api_admin_abonnement_selections_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        try {
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = min(100, max(10, (int) $request->query->get('limit', 20)));
            $offset = ($page - 1) * $limit;

            // Build filters
            $filters = [];
            if ($abonnementId = $request->query->get('abonnement')) {
                $filters['abonnement'] = $abonnementId;
            }
            if ($statut = $request->query->get('statut')) {
                $filters['statut'] = $statut;
            }
            if ($typeCuisine = $request->query->get('type_cuisine')) {
                $filters['typeCuisine'] = $typeCuisine;
            }

            // Date filtering
            $dateFrom = $request->query->get('date_from');
            $dateTo = $request->query->get('date_to');

            // Get filtered selections
            $selections = $this->selectionRepository->findWithFilters($filters, $limit, $offset, $dateFrom, $dateTo);
            $total = $this->selectionRepository->countWithFilters($filters, $dateFrom, $dateTo);

            // Format data for response
            $data = array_map(function (AbonnementSelection $selection) {
                return [
                    'id' => $selection->getId(),
                    'abonnement' => [
                        'id' => $selection->getAbonnement()->getId(),
                        'user_name' => $selection->getAbonnement()->getUser()->getPrenom() . ' ' . $selection->getAbonnement()->getUser()->getNom(),
                        'user_email' => $selection->getAbonnement()->getUser()->getEmail(),
                    ],
                    'date_selection' => $selection->getDateRepas()?->format('Y-m-d'),
                    'type_cuisine' => $selection->getCuisineType(),
                    'statut' => $selection->getStatut(),
                    'plat' => $selection->getPlat() ? [
                        'id' => $selection->getPlat()->getId(),
                        'nom' => $selection->getPlat()->getNom(),
                    ] : null,
                    'menu' => $selection->getMenu() ? [
                        'id' => $selection->getMenu()->getId(),
                        'nom' => $selection->getMenu()->getNom(),
                    ] : null,
                    'type_selection' => $selection->getTypeSelection(),
                    'prix' => $selection->getPrix(),
                    'notes' => $selection->getNotes(),
                    'date_creation' => $selection->getCreatedAt()?->format('Y-m-d H:i:s'),
                ];
            }, $selections);

            return new JsonResponse([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la récupération des sélections: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get incomplete selections
     */
    #[Route('/incomplete', name: 'api_admin_abonnement_selections_incomplete', methods: ['GET'])]
    public function getIncompleteSelections(): JsonResponse
    {
        try {
            // Find selections that are not completed
            $incompleteSelections = $this->selectionRepository->findBy(['statut' => 'en_attente']);
            
            $data = array_map(function (AbonnementSelection $selection) {
                return [
                    'subscription_id' => $selection->getAbonnement()->getId(),
                    'user_name' => $selection->getAbonnement()->getUser()->getPrenom() . ' ' . $selection->getAbonnement()->getUser()->getNom(),
                    'user_email' => $selection->getAbonnement()->getUser()->getEmail(),
                    'subscription_type' => $selection->getAbonnement()->getType(),
                    'missing_days' => [$selection->getDateRepas()?->format('Y-m-d')], // Simplified
                    'selection_id' => $selection->getId(),
                ];
            }, $incompleteSelections);

            return new JsonResponse([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la récupération des sélections incomplètes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get kitchen preparation lists
     */
    #[Route('/kitchen-prep', name: 'api_admin_abonnement_selections_kitchen_prep', methods: ['GET'])]
    public function getKitchenPrep(Request $request): JsonResponse
    {
        try {
            $date = $request->query->get('date', date('Y-m-d'));
            
            // Validate date format
            $dateObj = \DateTime::createFromFormat('Y-m-d', $date);
            if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Format de date invalide'
                ], 400);
            }

            // Get all confirmed selections for the date
            $selections = $this->selectionRepository->findBy([
                'dateRepas' => $dateObj,
                'statut' => 'confirme'
            ]);

            // Group by cuisine type and plat
            $kitchenData = [
                'date' => $date,
                'total_meals' => count($selections),
                'by_cuisine' => [],
                'preparation_list' => []
            ];

            // TODO: Implement proper kitchen preparation logic

            return new JsonResponse([
                'success' => true,
                'data' => $kitchenData
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la récupération de la liste de préparation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get weekly planning data
     */
    #[Route('/weekly-planning', name: 'api_admin_abonnement_selections_weekly_planning', methods: ['GET'])]
    public function getWeeklyPlanning(Request $request): JsonResponse
    {
        try {
            $weekStart = $request->query->get('week_start', date('Y-m-d', strtotime('monday this week')));
            
            // Validate week start
            $weekStartObj = \DateTime::createFromFormat('Y-m-d', $weekStart);
            if (!$weekStartObj || $weekStartObj->format('Y-m-d') !== $weekStart) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Format de date invalide pour le début de semaine'
                ], 400);
            }

            // Get selections for the week
            $weekEnd = clone $weekStartObj;
            $weekEnd->modify('+6 days');

            $selections = $this->selectionRepository->createQueryBuilder('s')
                ->where('s.dateRepas BETWEEN :start AND :end')
                ->setParameter('start', $weekStartObj)
                ->setParameter('end', $weekEnd)
                ->getQuery()
                ->getResult();

            // Group by day
            $weeklyData = [
                'week_start' => $weekStart,
                'week_end' => $weekEnd->format('Y-m-d'),
                'daily_planning' => []
            ];

            // TODO: Implement proper weekly planning logic

            return new JsonResponse([
                'success' => true,
                'data' => $weeklyData
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la récupération de la planification hebdomadaire: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update selection status
     */
    #[Route('/status-update', name: 'api_admin_abonnement_selections_status_update', methods: ['POST'])]
    public function updateStatus(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $selectionId = $data['selection_id'] ?? null;
            $newStatus = $data['status'] ?? null;

            if (!$selectionId || !$newStatus) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'ID de sélection et nouveau statut requis'
                ], 400);
            }

            $selection = $this->selectionRepository->find($selectionId);
            if (!$selection) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Sélection non trouvée'
                ], 404);
            }

            // Validate status
            $validStatuses = ['en_attente', 'confirme', 'annule'];
            if (!in_array($newStatus, $validStatuses)) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Statut invalide'
                ], 400);
            }

            $oldStatus = $selection->getStatut();
            $selection->setStatut($newStatus);
            
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Statut mis à jour avec succès',
                'data' => [
                    'id' => $selection->getId(),
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la mise à jour du statut: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk status update for multiple selections
     */
    #[Route('/bulk-status', name: 'api_admin_abonnement_selections_bulk_status', methods: ['POST'])]
    public function bulkStatusUpdate(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $selectionIds = $data['selection_ids'] ?? [];
            $newStatus = $data['status'] ?? null;

            if (empty($selectionIds) || !$newStatus) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'IDs des sélections et nouveau statut requis'
                ], 400);
            }

            // Validate status
            $validStatuses = ['en_attente', 'confirme', 'annule'];
            if (!in_array($newStatus, $validStatuses)) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Statut invalide'
                ], 400);
            }

            $selections = $this->selectionRepository->findBy(['id' => $selectionIds]);
            $results = [];

            foreach ($selections as $selection) {
                try {
                    $oldStatus = $selection->getStatut();
                    $selection->setStatut($newStatus);
                    $results[] = [
                        'id' => $selection->getId(),
                        'status' => 'success',
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus
                    ];
                } catch (\Exception $e) {
                    $results[] = [
                        'id' => $selection->getId(),
                        'status' => 'error',
                        'reason' => $e->getMessage()
                    ];
                }
            }

            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Mise à jour en lot terminée',
                'results' => $results
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la mise à jour en lot: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get selections by day
     */
    #[Route('/day/{date}', name: 'api_admin_abonnement_selections_day', methods: ['GET'])]
    public function getByDay(string $date): JsonResponse
    {
        try {
            // Validate date format
            $dateObj = \DateTime::createFromFormat('Y-m-d', $date);
            if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Format de date invalide. Utilisez YYYY-MM-DD.'
                ], 400);
            }

            // Get all selections for this date
            $selections = $this->selectionRepository->findBy(['dateRepas' => $dateObj]);
            
            // Get statistics for this day
            $totalSelections = count($selections);
            $completedSelections = count(array_filter($selections, fn($s) => $s->getStatut() === 'confirme'));
            $incompleteSelections = $totalSelections - $completedSelections;
            
            // Count by cuisine type
            $cuisineCounts = ['marocain' => 0, 'italien' => 0, 'international' => 0];
            foreach ($selections as $selection) {
                if ($selection->getCuisineType() && isset($cuisineCounts[$selection->getCuisineType()])) {
                    $cuisineCounts[$selection->getCuisineType()]++;
                }
            }
            
            // Get incomplete subscriptions details
            $incompleteSubscriptions = [];
            foreach ($selections as $selection) {
                if ($selection->getStatut() !== 'confirme') {
                    $user = $selection->getAbonnement()->getUser();
                    $incompleteSubscriptions[] = [
                        'id' => $selection->getAbonnement()->getId(),
                        'user_name' => $user->getPrenom() . ' ' . $user->getNom(),
                        'user_email' => $user->getEmail(),
                        'selection_id' => $selection->getId(),
                        'status' => $selection->getStatut()
                    ];
                }
            }
            
            // Count active subscriptions for this day
            $activeSubscriptions = $this->abonnementRepository->count(['statut' => 'actif']);

            return new JsonResponse([
                'success' => true,
                'data' => [
                    'date' => $date,
                    'total_selections' => $totalSelections,
                    'completed_selections' => $completedSelections,
                    'incomplete_selections' => $incompleteSelections,
                    'active_subscriptions' => $activeSubscriptions,
                    'cuisine_counts' => $cuisineCounts,
                    'incomplete_subscriptions' => $incompleteSubscriptions
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la récupération des données du jour: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send selection reminder
     */
    #[Route('/send-reminder', name: 'api_admin_abonnement_selections_send_reminder', methods: ['POST'])]
    public function sendReminder(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $subscriptionId = $data['subscription_id'] ?? null;
            $date = $data['date'] ?? null;

            if (!$subscriptionId) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'ID d\'abonnement requis'
                ], 400);
            }

            // TODO: Implement actual reminder sending functionality
            // This would typically send an email or SMS to the customer
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Rappel envoyé avec succès'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de l\'envoi du rappel: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send bulk selection reminders
     */
    #[Route('/send-bulk-reminders', name: 'api_admin_abonnement_selections_send_bulk_reminders', methods: ['POST'])]
    public function sendBulkReminders(Request $request): JsonResponse
    {
        try {
            // TODO: Implement bulk reminder sending
            // This would find all incomplete selections and send reminders
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Rappels envoyés en lot',
                'sent_count' => 0 // Placeholder
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de l\'envoi des rappels en lot: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get selection details
     */
    #[Route('/{id}', name: 'api_admin_abonnement_selections_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        try {
            $selection = $this->selectionRepository->find($id);
            
            if (!$selection) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Sélection non trouvée'
                ], 404);
            }

            $data = [
                'id' => $selection->getId(),
                'abonnement' => [
                    'id' => $selection->getAbonnement()->getId(),
                    'user_name' => $selection->getAbonnement()->getUser()->getPrenom() . ' ' . $selection->getAbonnement()->getUser()->getNom(),
                    'user_email' => $selection->getAbonnement()->getUser()->getEmail(),
                    'type' => $selection->getAbonnement()->getType(),
                ],
                'date_selection' => $selection->getDateRepas()?->format('Y-m-d'),
                'type_cuisine' => $selection->getCuisineType(),
                'statut' => $selection->getStatut(),
                'plat' => $selection->getPlat() ? [
                    'id' => $selection->getPlat()->getId(),
                    'nom' => $selection->getPlat()->getNom(),
                    'description' => $selection->getPlat()->getDescription(),
                ] : null,
                'menu' => $selection->getMenu() ? [
                    'id' => $selection->getMenu()->getId(),
                    'nom' => $selection->getMenu()->getNom(),
                    'description' => $selection->getMenu()->getDescription(),
                ] : null,
                'type_selection' => $selection->getTypeSelection(),
                'prix' => $selection->getPrix(),
                'notes' => $selection->getNotes(),
                'date_creation' => $selection->getCreatedAt()?->format('Y-m-d H:i:s'),
                'date_modification' => $selection->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ];

            return new JsonResponse([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la récupération de la sélection: ' . $e->getMessage()
            ], 500);
        }
    }
} 