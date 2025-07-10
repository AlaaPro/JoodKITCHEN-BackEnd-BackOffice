<?php

namespace App\Service;

use App\Entity\Commande;
use App\Entity\CommandeArticle;
use Doctrine\ORM\EntityManagerInterface;

class OrderDisplayService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Get formatted order details with all article information
     */
    public function getOrderDetails(Commande $commande): array
    {
        $articles = [];
        $totalItems = 0;
        $totalDeleted = 0;
        $totalAmount = 0;

        foreach ($commande->getCommandeArticles() as $article) {
            $itemInfo = $article->getItemInfo();
            $articles[] = $itemInfo;
            
            $totalItems++;
            if ($itemInfo['isDeleted']) {
                $totalDeleted++;
            }
            
            $totalAmount += $itemInfo['total'];
        }

        return [
            'order' => [
                'id' => $commande->getId(),
                'numeroCommande' => $this->generateOrderNumber($commande),
                'status' => $commande->getStatut(),
                'dateCommande' => $commande->getDateCommande()?->format('d/m/Y H:i'),
                'client' => $this->getClientInfo($commande),
                'delivery' => $this->getDeliveryInfo($commande),
                'totals' => [
                    'items' => $totalItems,
                    'deleted' => $totalDeleted,
                    'amount' => $totalAmount,
                    'discount' => $this->calculateTotalReduction($commande),
                    'final' => (float) $commande->getTotal()
                ]
            ],
            'articles' => $articles,
            'stats' => [
                'hasDeletedItems' => $totalDeleted > 0,
                'deletedPercentage' => $totalItems > 0 ? round(($totalDeleted / $totalItems) * 100, 1) : 0
            ]
        ];
    }

    /**
     * Get simplified article list for quick display
     */
    public function getArticlesList(Commande $commande): array
    {
        $articles = [];
        
        foreach ($commande->getCommandeArticles() as $article) {
            $articles[] = [
                'id' => $article->getId(),
                'name' => $article->getDisplayName(),
                'type' => $article->getItemType(),
                'quantity' => $article->getQuantite(),
                'price' => $article->getPrixUnitaire(),
                'total' => $article->getQuantite() * $article->getPrixUnitaire(),
                'isDeleted' => $article->isDeleted(),
                'comment' => $article->getCommentaire()
            ];
        }

        return $articles;
    }

    /**
     * Get order summary for tables and lists
     */
    public function getOrderSummary(Commande $commande): array
    {
        $articles = $this->getArticlesList($commande);
        $deletedCount = count(array_filter($articles, fn($a) => $a['isDeleted']));

        return [
            'id' => $commande->getId(),
            'numeroCommande' => $this->generateOrderNumber($commande),
            'status' => $commande->getStatut(),
            'date' => $commande->getDateCommande()?->format('d/m/Y H:i'),
            'clientName' => $this->getClientName($commande),
            'itemsCount' => count($articles),
            'deletedItemsCount' => $deletedCount,
            'total' => (float) $commande->getTotal(),
            'hasIssues' => $deletedCount > 0
        ];
    }

    /**
     * Check if an order has any deleted items
     */
    public function hasDeletedItems(Commande $commande): bool
    {
        foreach ($commande->getCommandeArticles() as $article) {
            if ($article->isDeleted()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get count of deleted items in an order
     */
    public function getDeletedItemsCount(Commande $commande): int
    {
        $count = 0;
        foreach ($commande->getCommandeArticles() as $article) {
            if ($article->isDeleted()) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Get order validation status
     */
    public function validateOrder(Commande $commande): array
    {
        $issues = [];
        $warnings = [];

        foreach ($commande->getCommandeArticles() as $article) {
            if ($article->isDeleted()) {
                $issues[] = "Article supprimé: {$article->getDisplayName()}";
            }

            if (!$article->getNomOriginal() && !$article->isDeleted()) {
                $warnings[] = "Article sans historique: {$article->getDisplayName()}";
            }
        }

        return [
            'isValid' => empty($issues),
            'hasWarnings' => !empty($warnings),
            'issues' => $issues,
            'warnings' => $warnings,
            'score' => $this->calculateOrderHealthScore($commande)
        ];
    }

    private function getClientInfo(Commande $commande): ?array
    {
        $user = $commande->getUser();
        if (!$user) {
            return null;
        }

        $clientProfile = $user->getClientProfile();
        
        return [
            'id' => $user->getId(),
            'nom' => $user->getNom(),
            'prenom' => $user->getPrenom(),
            'email' => $user->getEmail(),
            'telephone' => $user->getTelephone(), // From User entity
            'adresse' => $clientProfile?->getAdresseLivraison()
        ];
    }

    private function getDeliveryInfo(Commande $commande): ?array
    {
        return [
            'adresse' => $commande->getAdresseLivraison(),
            'type' => $commande->getTypeLivraison(),
            'commentaire' => $commande->getCommentaire()
        ];
    }

    private function getClientName(Commande $commande): string
    {
        $user = $commande->getUser();
        if (!$user) {
            return 'Client inconnu';
        }

        return trim($user->getPrenom() . ' ' . $user->getNom()) ?: $user->getEmail();
    }

    private function calculateOrderHealthScore(Commande $commande): int
    {
        $totalArticles = count($commande->getCommandeArticles());
        if ($totalArticles === 0) {
            return 0;
        }

        $healthyArticles = 0;
        foreach ($commande->getCommandeArticles() as $article) {
            if (!$article->isDeleted()) {
                $healthyArticles++;
            }
        }

        return round(($healthyArticles / $totalArticles) * 100);
    }

    /**
     * Generate order number format (CMD-XXX)
     */
    private function generateOrderNumber(Commande $commande): string
    {
        return 'CMD-' . str_pad((string) $commande->getId(), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Calculate total reduction amount from all reductions
     */
    private function calculateTotalReduction(Commande $commande): float
    {
        $totalBefore = (float) ($commande->getTotalAvantReduction() ?? $commande->getTotal());
        $totalAfter = (float) $commande->getTotal();
        
        return max(0.0, $totalBefore - $totalAfter);
    }
} 