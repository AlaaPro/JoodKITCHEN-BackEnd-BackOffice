<?php

namespace App\Service;

use App\Entity\Abonnement;
use App\Entity\AbonnementSelection;
use App\Entity\Menu;
use App\Entity\Plat;
use App\Entity\User;
use App\Entity\Payment;
use App\Repository\AbonnementRepository;
use App\Repository\AbonnementSelectionRepository;
use App\Repository\MenuRepository;
use App\Repository\PlatRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class SubscriptionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AbonnementRepository $abonnementRepository,
        private AbonnementSelectionRepository $abonnementSelectionRepository,
        private MenuRepository $menuRepository,
        private PlatRepository $platRepository
    ) {}

    /**
     * Create a new weekly subscription for a user
     */
    public function createWeeklySubscription(User $user, \DateTimeInterface $dateDebut): Abonnement
    {
        // Calculate end date (1 week from start)
        $dateFin = (new \DateTime())->setTimestamp($dateDebut->getTimestamp());
        $dateFin->add(new \DateInterval('P6D')); // Add 6 days to make it a full week

        $abonnement = new Abonnement();
        $abonnement->setUser($user);
        $abonnement->setType('hebdo');
        $abonnement->setRepasParJour(1); // 1 meal per day
        $abonnement->setDateDebut($dateDebut);
        $abonnement->setDateFin($dateFin);
        $abonnement->setStatut('actif');

        $this->entityManager->persist($abonnement);
        $this->entityManager->flush();

        return $abonnement;
    }

    /**
     * Add a meal selection to a subscription
     */
    public function addMealSelection(
        Abonnement $abonnement,
        \DateTimeInterface $dateRepas,
        string $typeSelection,
        ?Menu $menu = null,
        ?Plat $plat = null,
        ?string $cuisineType = null
    ): AbonnementSelection {
        // Validate selection type
        $this->validateMealSelection($typeSelection, $menu, $plat, $cuisineType);
        
        // Check if selection already exists for this date
        $existingSelection = $this->abonnementSelectionRepository
            ->createQueryBuilder('abs')
            ->join('abs.abonnement', 'a')
            ->andWhere('a.id = :abonnementId')
            ->andWhere('abs.dateRepas = :dateRepas')
            ->setParameter('abonnementId', $abonnement->getId())
            ->setParameter('dateRepas', $dateRepas)
            ->getQuery()
            ->getOneOrNullResult();

        if ($existingSelection) {
            throw new BadRequestException('Une sélection existe déjà pour cette date');
        }

        // Create selection
        $selection = new AbonnementSelection();
        $selection->setAbonnement($abonnement);
        $selection->setDateRepas($dateRepas);
        $selection->setJourSemaine($this->getDayOfWeek($dateRepas));
        $selection->setTypeSelection($typeSelection);
        $selection->setCuisineType($cuisineType);

        // Set menu or dish
        if ($menu) {
            $selection->setMenu($menu);
            $selection->setPrix($menu->getPrix());
        } elseif ($plat) {
            $selection->setPlat($plat);
            $selection->setPrix($plat->getPrix());
        }

        $this->entityManager->persist($selection);
        $this->entityManager->flush();

        return $selection;
    }

    /**
     * Get available daily menus for a specific date
     */
    public function getAvailableDailyMenus(\DateTimeInterface $date): array
    {
        return $this->menuRepository->findBy([
            'type' => 'menu_du_jour',
            'date' => $date,
            'actif' => true
        ], ['tag' => 'ASC']);
    }

    /**
     * Get available normal menus
     */
    public function getAvailableNormalMenus(): array
    {
        return $this->menuRepository->findBy([
            'type' => 'normal',
            'actif' => true
        ], ['nom' => 'ASC']);
    }

    /**
     * Get menu du jour for specific cuisine and date
     */
    public function getMenuDuJour(\DateTimeInterface $date, string $cuisineType): ?Menu
    {
        return $this->menuRepository->findOneBy([
            'type' => 'menu_du_jour',
            'date' => $date,
            'tag' => $cuisineType,
            'actif' => true
        ]);
    }

    /**
     * Calculate subscription total with discount
     */
    public function calculateSubscriptionTotal(Abonnement $abonnement): array
    {
        $selections = $abonnement->getSelections();
        $subtotal = 0.0;
        $mealCount = 0;

        foreach ($selections as $selection) {
            $subtotal += (float) $selection->getPrix();
            $mealCount++;
        }

        $discountRate = $abonnement->getWeeklyDiscountRate();
        $discountAmount = $subtotal * $discountRate;
        $total = $subtotal - $discountAmount;

        return [
            'subtotal' => $subtotal,
            'discount_rate' => $discountRate,
            'discount_amount' => $discountAmount,
            'total' => $total,
            'meal_count' => $mealCount,
            'expected_meals' => 5 // Monday to Friday
        ];
    }

    /**
     * Create subscription payment
     */
    public function createSubscriptionPayment(
        Abonnement $abonnement,
        string $methodePaiement,
        float $montant,
        ?string $referenceTransaction = null
    ): Payment {
        $payment = new Payment();
        $payment->setAbonnement($abonnement);
        $payment->setTypePaiement('abonnement');
        $payment->setMontant((string) $montant);
        $payment->setMethodePaiement($methodePaiement);
        $payment->setReferenceTransaction($referenceTransaction);
        
        // Set status based on payment method
        if ($methodePaiement === 'cmi') {
            $payment->setStatut('en_attente'); // Will be validated by CMI
        } elseif ($methodePaiement === 'cash_on_delivery') {
            $payment->setStatut('en_attente'); // Will be paid on first delivery
        } else {
            $payment->setStatut('valide');
        }

        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        return $payment;
    }

    /**
     * Get weekly planning for a subscription
     */
    public function getWeeklyPlanning(Abonnement $abonnement, ?\DateTimeInterface $weekStart = null): array
    {
        if (!$weekStart) {
            $weekStart = $abonnement->getDateDebut();
        }

        $selections = $this->abonnementSelectionRepository->findByWeek(
            $abonnement->getId(),
            $weekStart
        );

        $planning = [];
        $days = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi'];
        
        foreach ($days as $day) {
            $planning[$day] = [
                'day' => $day,
                'date' => null,
                'selection' => null,
                'available_menus' => []
            ];
        }

        // Fill with actual selections
        foreach ($selections as $selection) {
            $day = $selection->getJourSemaine();
            $planning[$day]['selection'] = $selection;
            $planning[$day]['date'] = $selection->getDateRepas();
        }

        // Get available menus for each day without selection
        foreach ($planning as $day => &$dayData) {
            if (!$dayData['selection'] && $dayData['date']) {
                $dayData['available_menus'] = $this->getAvailableDailyMenus($dayData['date']);
            }
        }

        return array_values($planning);
    }

    /**
     * Validate meal selection parameters
     */
    private function validateMealSelection(string $typeSelection, ?Menu $menu, ?Plat $plat, ?string $cuisineType): void
    {
        switch ($typeSelection) {
            case 'menu_du_jour':
                if (!$menu || !$cuisineType) {
                    throw new BadRequestException('Menu et type de cuisine requis pour menu du jour');
                }
                if ($menu->getType() !== 'menu_du_jour') {
                    throw new BadRequestException('Le menu sélectionné n\'est pas un menu du jour');
                }
                break;

            case 'menu_normal':
                if (!$menu) {
                    throw new BadRequestException('Menu requis pour menu normal');
                }
                if ($menu->getType() !== 'normal') {
                    throw new BadRequestException('Le menu sélectionné n\'est pas un menu normal');
                }
                break;

            case 'plat_individuel':
                if (!$plat) {
                    throw new BadRequestException('Plat requis pour plat individuel');
                }
                break;

            default:
                throw new BadRequestException('Type de sélection invalide');
        }
    }

    /**
     * Get day of week in French
     */
    private function getDayOfWeek(\DateTimeInterface $date): string
    {
        $dayNumber = (int) $date->format('N'); // 1 = Monday, 7 = Sunday
        $days = [
            1 => 'lundi',
            2 => 'mardi', 
            3 => 'mercredi',
            4 => 'jeudi',
            5 => 'vendredi',
            6 => 'samedi',
            7 => 'dimanche'
        ];

        return $days[$dayNumber] ?? 'inconnu';
    }

    /**
     * Confirm all selections for a subscription week
     */
    public function confirmWeeklySelections(Abonnement $abonnement): bool
    {
        $selections = $abonnement->getSelections();
        $confirmed = 0;

        foreach ($selections as $selection) {
            if ($selection->getStatut() === 'selectionne') {
                $selection->setStatut('confirme');
                $confirmed++;
            }
        }

        if ($confirmed > 0) {
            $this->entityManager->flush();
            return true;
        }

        return false;
    }

    /**
     * Get subscription statistics
     */
    public function getSubscriptionStats(): array
    {
        // Active subscriptions
        $activeSubscriptions = $this->abonnementRepository->findActiveSubscriptions();
        
        // Weekly subscriptions vs monthly
        $weeklyCount = count(array_filter($activeSubscriptions, fn($s) => $s->getType() === 'hebdo'));
        $monthlyCount = count(array_filter($activeSubscriptions, fn($s) => $s->getType() === 'mensuel'));

        // Most popular cuisine types (from selections)
        $cuisineStats = $this->abonnementSelectionRepository
            ->createQueryBuilder('abs')
            ->select('abs.cuisineType, COUNT(abs.id) as count')
            ->andWhere('abs.cuisineType IS NOT NULL')
            ->andWhere('abs.statut IN (:statuts)')
            ->setParameter('statuts', ['confirme', 'prepare', 'livre'])
            ->groupBy('abs.cuisineType')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getArrayResult();

        return [
            'total_active' => count($activeSubscriptions),
            'weekly_subscriptions' => $weeklyCount,
            'monthly_subscriptions' => $monthlyCount,
            'cuisine_popularity' => $cuisineStats,
            'generated_at' => (new \DateTime())->format('Y-m-d H:i:s')
        ];
    }
} 