<?php

namespace App\Controller\Api;

use App\Entity\Abonnement;
use App\Entity\AbonnementSelection;
use App\Repository\AbonnementRepository;
use App\Repository\AbonnementSelectionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * AbonnementController - Handles subscription management API endpoints
 * 
 * This controller provides comprehensive subscription management for the admin interface:
 * - CRUD operations for subscriptions
 * - Statistics and analytics
 * - Calendar view data
 * - Status management
 * - Bulk operations
 * - Export functionality
 */
#[Route('/api/admin/abonnements')]
#[IsGranted('ROLE_ADMIN')]
class AbonnementController extends AbstractController
{
    public function __construct(
        private AbonnementRepository $abonnementRepository,
        private AbonnementSelectionRepository $selectionRepository,
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer
    ) {}

    /**
     * Get all subscriptions with filtering and pagination
     */
    #[Route('', name: 'api_admin_abonnements_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        try {
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = min(100, max(10, (int) $request->query->get('limit', 20)));
            $offset = ($page - 1) * $limit;

            // Build filters
            $filters = [];
            if ($status = $request->query->get('status')) {
                $filters['statut'] = $status;
            }
            if ($type = $request->query->get('type')) {
                $filters['type'] = $type;
            }
            if ($search = $request->query->get('search')) {
                // Will be handled in repository
                $filters['search'] = $search;
            }

            // Date range filtering
            $dateFrom = $request->query->get('date_from');
            $dateTo = $request->query->get('date_to');

            // Get filtered subscriptions
            $subscriptions = $this->abonnementRepository->findWithFilters($filters, $limit, $offset, $dateFrom, $dateTo);
            $total = $this->abonnementRepository->countWithFilters($filters, $dateFrom, $dateTo);

            // Format data for response
            $data = array_map(function (Abonnement $abonnement) {
                return [
                    'id' => $abonnement->getId(),
                    'user' => [
                        'id' => $abonnement->getUser()->getId(),
                        'nom' => $abonnement->getUser()->getNom(),
                        'prenom' => $abonnement->getUser()->getPrenom(),
                        'email' => $abonnement->getUser()->getEmail(),
                        'telephone' => $abonnement->getUser()->getTelephone(),
                    ],
                    'type' => $abonnement->getType(),
                    'type_label' => $abonnement->getTypeLabel(),
                    'statut' => $abonnement->getStatut(),
                    'statut_label' => $abonnement->getStatutLabel(),
                    'statut_color' => $abonnement->getStatusColor(),
                    'statut_icon' => $abonnement->getStatusIcon(),
                    'date_creation' => $abonnement->getCreatedAt()->format('Y-m-d H:i:s'),
                    'date_debut' => $abonnement->getDateDebut()?->format('Y-m-d'),
                    'date_fin' => $abonnement->getDateFin()?->format('Y-m-d'),
                    'montant' => $abonnement->calculateWeeklyPrice(), // Calculate price from selections
                    'weekly_price' => $abonnement->calculateWeeklyPrice(), // Frontend expects this field name
                    'discount_rate' => $abonnement->getWeeklyDiscountRate() * 100, // Convert to percentage
                    'selections_count' => $abonnement->getSelections()->count(), // Number of meal selections
                    'mode_paiement' => 'cmi', // Default for now - TODO: add property to entity
                    'nb_repas' => $abonnement->getRepasParJour(),
                    'requires_cmi_payment' => $abonnement->requiresCMIPayment(),
                    'is_waiting_for_confirmation' => $abonnement->isWaitingForConfirmation(),
                    'can_be_activated' => $abonnement->canBeActivated(),
                    'can_be_suspended' => $abonnement->canBeSuspended(),
                ];
            }, $subscriptions);

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
                'error' => 'Erreur lors de la récupération des abonnements: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get subscription statistics for dashboard
     */
    #[Route('/stats', name: 'api_admin_abonnements_stats', methods: ['GET'])]
    public function getStatistics(): JsonResponse
    {
        try {
            // Get subscription counts by status
            $stats = [
                'overview' => [
                    'total' => $this->abonnementRepository->count([]),
                    'en_confirmation' => $this->abonnementRepository->count(['statut' => 'en_confirmation']),
                    'actif' => $this->abonnementRepository->count(['statut' => 'actif']),
                    'suspendu' => $this->abonnementRepository->count(['statut' => 'suspendu']),
                    'expire' => $this->abonnementRepository->count(['statut' => 'expire']),
                    'annule' => $this->abonnementRepository->count(['statut' => 'annule']),
                ],
                'revenue' => [
                    'weekly_total' => 0, // TODO: Calculate from active subscriptions
                    'monthly_total' => 0,
                    'average_subscription_value' => 0,
                    'growth_rate' => 0,
                ],
                'conversion' => [
                    'conversion_rate' => 0, // TODO: Calculate based on en_confirmation -> actif
                ],
                'alerts' => [] // TODO: Add system alerts
            ];

            return new JsonResponse([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la récupération des statistiques: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pending confirmations count for sidebar badge
     */
    #[Route('/pending-count', name: 'api_admin_abonnements_pending_count', methods: ['GET'])]
    public function getPendingCount(): JsonResponse
    {
        try {
            $count = $this->abonnementRepository->count(['statut' => 'en_confirmation']);

            return new JsonResponse([
                'success' => true,
                'count' => $count
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors du comptage: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get calendar data for weekly view
     */
    #[Route('/calendar', name: 'api_admin_abonnements_calendar', methods: ['GET'])]
    public function getCalendarData(Request $request): JsonResponse
    {
        try {
            $weekStart = $request->query->get('week_start', date('Y-m-d', strtotime('monday this week')));
            
            // Get real meal selections for the week
            $weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));
            
            $selections = $this->selectionRepository->createQueryBuilder('s')
                ->leftJoin('s.abonnement', 'a')
                ->leftJoin('a.user', 'u')
                ->addSelect('a', 'u')
                ->where('s.dateRepas BETWEEN :weekStart AND :weekEnd')
                ->andWhere('a.statut IN (:allowedStatuses)')
                ->setParameter('weekStart', new \DateTime($weekStart))
                ->setParameter('weekEnd', new \DateTime($weekEnd))
                ->setParameter('allowedStatuses', ['actif', 'en_confirmation'])
                ->orderBy('s.dateRepas', 'ASC')
                ->getQuery()
                ->getResult();
            
            $dailyData = [];
            $totalWeekSelections = 0;
            
            for ($i = 0; $i < 7; $i++) {
                $date = date('Y-m-d', strtotime($weekStart . ' +' . $i . ' days'));
                $dayName = date('l', strtotime($date));
                
                // Filter selections for this specific date
                $daySelections = array_filter($selections, function($selection) use ($date) {
                    return $selection->getDateRepas()?->format('Y-m-d') === $date;
                });
                
                // Count by cuisine type
                $cuisineCounts = ['marocain' => 0, 'italien' => 0, 'international' => 0];
                $incompleteCount = 0;
                
                foreach ($daySelections as $selection) {
                    $cuisine = $selection->getCuisineType();
                    if ($cuisine && isset($cuisineCounts[$cuisine])) {
                        $cuisineCounts[$cuisine]++;
                    }
                    
                    // Count incomplete selections (not confirmed/completed)
                    if (!in_array($selection->getStatut(), ['confirme', 'prepare', 'livre'])) {
                        $incompleteCount++;
                    }
                }
                
                $dayTotalSelections = count($daySelections);
                $totalWeekSelections += $dayTotalSelections;
                
                $dailyData[] = [
                    'date' => $date,
                    'day_name' => $dayName,
                    'cuisine_counts' => $cuisineCounts,
                    'total_selections' => $dayTotalSelections,
                    'incomplete_count' => $incompleteCount,
                    'selections' => array_map(function($selection) {
                        return [
                            'id' => $selection->getId(),
                            'user_name' => $selection->getAbonnement()->getUser()->getPrenom() . ' ' . $selection->getAbonnement()->getUser()->getNom(),
                            'cuisine_type' => $selection->getCuisineType(),
                            'statut' => $selection->getStatut(),
                            'plat_nom' => $selection->getPlat()?->getNom(),
                            'menu_nom' => $selection->getMenu()?->getNom(),
                        ];
                    }, $daySelections)
                ];
            }

            return new JsonResponse([
                'success' => true,
                'data' => [
                    'week_start' => $weekStart,
                    'week_end' => $weekEnd,
                    'total_week_selections' => $totalWeekSelections,
                    'daily_data' => $dailyData
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la récupération du calendrier: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get cuisine statistics
     */
    #[Route('/cuisine-stats', name: 'api_admin_abonnements_cuisine_stats', methods: ['GET'])]
    public function getCuisineStats(): JsonResponse
    {
        try {
            // TODO: Implement actual cuisine statistics
            $stats = [
                'marocain' => ['count' => 45, 'percentage' => 55],
                'italien' => ['count' => 25, 'percentage' => 30],
                'international' => ['count' => 12, 'percentage' => 15]
            ];

            return new JsonResponse([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la récupération des statistiques culinaires: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single subscription details
     */
    #[Route('/{id}', name: 'api_admin_abonnements_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        try {
            $abonnement = $this->abonnementRepository->find($id);
            
            if (!$abonnement) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Abonnement non trouvé'
                ], 404);
            }

            $data = [
                'id' => $abonnement->getId(),
                'user' => [
                    'id' => $abonnement->getUser()->getId(),
                    'nom' => $abonnement->getUser()->getNom(),
                    'prenom' => $abonnement->getUser()->getPrenom(),
                    'email' => $abonnement->getUser()->getEmail(),
                    'telephone' => $abonnement->getUser()->getTelephone(),
                    'adresse' => $abonnement->getUser()->getAdresse(),
                    'ville' => $abonnement->getUser()->getVille(),
                    'adresse_livraison' => $abonnement->getUser()->getClientProfile()?->getAdresseLivraison(),
                ],
                'type' => $abonnement->getType(),
                'type_label' => $abonnement->getTypeLabel(),
                'statut' => $abonnement->getStatut(),
                'statut_label' => $abonnement->getStatutLabel(),
                'statut_color' => $abonnement->getStatusColor(),
                'statut_icon' => $abonnement->getStatusIcon(),
                'date_creation' => $abonnement->getCreatedAt()->format('Y-m-d H:i:s'),
                'date_debut' => $abonnement->getDateDebut()?->format('Y-m-d'),
                'date_fin' => $abonnement->getDateFin()?->format('Y-m-d'),
                'montant' => $abonnement->calculateWeeklyPrice(), // Calculate price from selections
                'weekly_price' => $abonnement->calculateWeeklyPrice(), // Frontend expects this field name
                'discount_rate' => $abonnement->getWeeklyDiscountRate() * 100, // Convert to percentage
                'selections_count' => $abonnement->getSelections()->count(), // Number of meal selections
                'mode_paiement' => 'cmi', // Default for now - TODO: add property to entity
                'nb_repas' => $abonnement->getRepasParJour(),
                'requires_cmi_payment' => $abonnement->requiresCMIPayment(),
                'is_waiting_for_confirmation' => $abonnement->isWaitingForConfirmation(),
                'can_be_activated' => $abonnement->canBeActivated(),
                'can_be_suspended' => $abonnement->canBeSuspended(),
            ];

            // Get meal selections for this subscription
            $selections = $this->selectionRepository->findBy(['abonnement' => $abonnement]);
            
            // Calculate selection statistics
            $totalSelections = count($selections);
            $completedSelections = 0;
            $pendingSelections = 0;
            $totalAmountPaid = 0.0;
            
            foreach ($selections as $selection) {
                // Count completed vs pending selections
                if (in_array($selection->getStatut(), ['confirme', 'prepare', 'livre'])) {
                    $completedSelections++;
                } else {
                    $pendingSelections++;
                }
                
                // Sum up selection prices
                $totalAmountPaid += (float) $selection->getPrix();
            }
            
            // Add statistics to response
            $data['selection_statistics'] = [
                'total_selections' => $totalSelections,
                'completed_selections' => $completedSelections,
                'pending_selections' => $pendingSelections,
                'total_amount' => number_format($totalAmountPaid, 2)
            ];
            
            $data['selections'] = array_map(function ($selection) {
                return [
                    'id' => $selection->getId(),
                    'date_selection' => $selection->getDateRepas()?->format('Y-m-d'),
                    'type_cuisine' => $selection->getCuisineType(),
                    'statut' => $selection->getStatut(),
                    'plat' => $selection->getPlat()?->getNom(),
                    'menu' => $selection->getMenu()?->getNom(),
                    'type_selection' => $selection->getTypeSelection(),
                    'prix' => $selection->getPrix(),
                    'notes' => $selection->getNotes(),
                ];
            }, $selections);

            return new JsonResponse([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la récupération de l\'abonnement: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update subscription status
     */
    #[Route('/status-update', name: 'api_admin_abonnements_status_update', methods: ['POST'])]
    public function updateStatus(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $abonnementId = $data['abonnement_id'] ?? null;
            $newStatus = $data['status'] ?? null;
            $reason = $data['reason'] ?? null;

            if (!$abonnementId || !$newStatus) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'ID de l\'abonnement et nouveau statut requis'
                ], 400);
            }

            $abonnement = $this->abonnementRepository->find($abonnementId);
            if (!$abonnement) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Abonnement non trouvé'
                ], 404);
            }

            // Validate status transition
            $validStatuses = ['en_confirmation', 'actif', 'suspendu', 'expire', 'annule'];
            if (!in_array($newStatus, $validStatuses)) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Statut invalide'
                ], 400);
            }

            $oldStatus = $abonnement->getStatut();
            $abonnement->setStatut($newStatus);
            
            $this->entityManager->flush();

            // TODO: Add audit log for status change
            // TODO: Send notification to customer if needed

            return new JsonResponse([
                'success' => true,
                'message' => 'Statut mis à jour avec succès',
                'data' => [
                    'id' => $abonnement->getId(),
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'statut_label' => $abonnement->getStatutLabel(),
                    'statut_color' => $abonnement->getStatusColor(),
                    'statut_icon' => $abonnement->getStatusIcon(),
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
     * Get status change history
     */
    #[Route('/status-update/history', name: 'api_admin_abonnements_status_history', methods: ['GET'])]
    public function getStatusHistory(Request $request): JsonResponse
    {
        try {
            $limit = min(50, max(10, (int) $request->query->get('limit', 20)));
            
            // TODO: Implement actual status history retrieval
            // This would typically query an audit log or status change history table
            
            // For now, return mock data
            $historyData = [
                [
                    'id' => 1,
                    'user_name' => 'Jean Dupont',
                    'old_status' => 'en_confirmation',
                    'new_status' => 'actif',
                    'reason' => 'Paiement confirmé',
                    'created_at' => (new \DateTime('-2 hours'))->format('Y-m-d H:i:s'),
                    'changed_by' => 'Admin'
                ],
                [
                    'id' => 2,
                    'user_name' => 'Marie Martin',
                    'old_status' => 'actif',
                    'new_status' => 'suspendu',
                    'reason' => 'Demande du client',
                    'created_at' => (new \DateTime('-1 day'))->format('Y-m-d H:i:s'),
                    'changed_by' => 'Admin'
                ]
            ];

            return new JsonResponse([
                'success' => true,
                'data' => $historyData
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la récupération de l\'historique: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk operations on multiple subscriptions
     */
    #[Route('/bulk', name: 'api_admin_abonnements_bulk', methods: ['POST'])]
    public function bulkOperations(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $action = $data['action'] ?? null;
            $subscriptionIds = $data['subscription_ids'] ?? [];

            if (!$action || empty($subscriptionIds)) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Action et IDs des abonnements requis'
                ], 400);
            }

            $subscriptions = $this->abonnementRepository->findBy(['id' => $subscriptionIds]);
            $results = [];

            foreach ($subscriptions as $abonnement) {
                try {
                    switch ($action) {
                        case 'activate':
                        case 'status_change':
                            $newStatus = $data['new_status'] ?? 'actif';
                            if ($abonnement->canBeActivated() || $newStatus !== 'actif') {
                                $abonnement->setStatut($newStatus);
                                $results[] = ['id' => $abonnement->getId(), 'status' => 'success', 'action' => 'updated'];
                            } else {
                                $results[] = ['id' => $abonnement->getId(), 'status' => 'skipped', 'reason' => 'Cannot be activated'];
                            }
                            break;
                        case 'suspend':
                            if ($abonnement->canBeSuspended()) {
                                $abonnement->setStatut('suspendu');
                                $results[] = ['id' => $abonnement->getId(), 'status' => 'success', 'action' => 'suspended'];
                            } else {
                                $results[] = ['id' => $abonnement->getId(), 'status' => 'skipped', 'reason' => 'Cannot be suspended'];
                            }
                            break;
                        case 'cancel':
                            $abonnement->setStatut('annule');
                            $results[] = ['id' => $abonnement->getId(), 'status' => 'success', 'action' => 'cancelled'];
                            break;
                        default:
                            $results[] = ['id' => $abonnement->getId(), 'status' => 'error', 'reason' => 'Unknown action'];
                    }
                } catch (\Exception $e) {
                    $results[] = ['id' => $abonnement->getId(), 'status' => 'error', 'reason' => $e->getMessage()];
                }
            }

            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Opérations en lot terminées',
                'results' => $results
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors des opérations en lot: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Auto-expire old subscriptions
     */
    #[Route('/bulk/auto-expire', name: 'api_admin_abonnements_bulk_auto_expire', methods: ['POST'])]
    public function autoExpire(Request $request): JsonResponse
    {
        try {
            // Find subscriptions that should be auto-expired
            // For example, subscriptions that have been inactive for more than 30 days
            $cutoffDate = new \DateTime('-30 days');
            
            // TODO: Implement proper logic to find subscriptions to expire
            // This might involve checking last activity, payment status, etc.
            
            $expiredCount = 0;
            
            // For now, just return a success response with count
            return new JsonResponse([
                'success' => true,
                'message' => 'Auto-expiration terminée',
                'expired_count' => $expiredCount
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de l\'auto-expiration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Propose renewal to expired subscriptions
     */
    #[Route('/bulk/propose-renewal', name: 'api_admin_abonnements_bulk_propose_renewal', methods: ['POST'])]
    public function proposeRenewal(Request $request): JsonResponse
    {
        try {
            // Find expired subscriptions
            $expiredSubscriptions = $this->abonnementRepository->findBy(['statut' => 'expire']);
            
            $sentCount = 0;
            
            // TODO: Implement actual renewal proposal sending
            // This would typically send emails to customers with expired subscriptions
            
            foreach ($expiredSubscriptions as $subscription) {
                // TODO: Send renewal proposal email
                $sentCount++;
            }

            return new JsonResponse([
                'success' => true,
                'message' => 'Propositions de renouvellement envoyées',
                'sent_count' => $sentCount
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de l\'envoi des propositions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export subscriptions data
     */
    #[Route('/export', name: 'api_admin_abonnements_export', methods: ['GET'])]
    public function export(Request $request): JsonResponse
    {
        try {
            $format = $request->query->get('format', 'csv'); // csv, excel, pdf
            $filters = $request->query->all();
            
            // TODO: Implement actual export functionality
            // For now, return export URL that would be handled by a separate service
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Export en cours de préparation',
                'download_url' => $this->generateUrl('api_admin_abonnements_download', ['format' => $format]),
                'estimated_time' => '30 seconds'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de l\'export: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export day-specific data
     */
    #[Route('/export/day/{date}', name: 'api_admin_abonnements_export_day', methods: ['GET'])]
    public function exportDay(string $date): JsonResponse
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

            // For now, return a success response with download URL
            // TODO: Implement actual day-specific export functionality
            return new JsonResponse([
                'success' => true,
                'message' => 'Export du jour en cours de préparation',
                'download_url' => $this->generateUrl('api_admin_abonnements_download', ['format' => 'csv']) . '?date=' . $date,
                'estimated_time' => '15 seconds'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de l\'export du jour: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download exported file
     */
    #[Route('/download/{format}', name: 'api_admin_abonnements_download', methods: ['GET'])]
    public function download(string $format): JsonResponse
    {
        // TODO: Implement actual file download
        return new JsonResponse([
            'success' => false,
            'error' => 'Fonctionnalité d\'export en cours de développement'
        ], 501);
    }

    /**
     * Get subscription payments
     */
    #[Route('/{id}/payments', name: 'api_admin_abonnements_payments', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getPayments(int $id): JsonResponse
    {
        try {
            $abonnement = $this->abonnementRepository->find($id);
            
            if (!$abonnement) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Abonnement non trouvé'
                ], 404);
            }

            // Get actual payment records from Payment entity
            $paymentEntities = $this->entityManager->getRepository(\App\Entity\Payment::class)
                ->findBy(['abonnement' => $abonnement]);
            
            $payments = array_map(function($payment) {
                return [
                    'id' => $payment->getId(),
                    'montant' => $payment->getMontant(),
                    'mode_paiement' => $payment->getMethodePaiement(),
                    'statut' => $payment->getStatut(),
                    'date_creation' => $payment->getCreatedAt()->format('Y-m-d H:i:s'),
                    'date_traitement' => $payment->getDatePaiement()?->format('Y-m-d H:i:s'),
                    'reference_externe' => $payment->getReferenceTransaction()
                ];
            }, $paymentEntities);
            
            // If no payments exist, create a placeholder for UI
            if (empty($payments)) {
                $payments = [
                    [
                        'id' => null,
                        'montant' => $abonnement->calculateWeeklyPrice(),
                        'mode_paiement' => 'cmi',
                        'statut' => 'en_attente',
                        'date_creation' => $abonnement->getCreatedAt()->format('Y-m-d H:i:s'),
                        'date_traitement' => null,
                        'reference_externe' => 'À créer'
                    ]
                ];
            }

            return new JsonResponse([
                'success' => true,
                'data' => [
                    'abonnement_id' => $id,
                    'payments' => $payments
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la récupération des paiements: ' . $e->getMessage()
            ], 500);
        }
    }
} 