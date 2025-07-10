<?php

namespace App\Service;

use App\Entity\CommandeArticle;
use App\Entity\Plat;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service to handle order history preservation
 * Creates snapshots of menu items when orders are placed
 */
class OrderHistoryService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Create snapshot data for an order article to preserve history
     */
    public function createArticleSnapshot(CommandeArticle $article): void
    {
        $plat = $article->getPlat();
        
        if ($plat) {
            // Store snapshot of current item data
            $article->setNomOriginal($plat->getNom());
            $article->setDescriptionOriginale($plat->getDescription());
            $article->setDateSnapshot(new \DateTime());
        }
    }

    /**
     * Create snapshots for all articles in an order
     */
    public function createOrderSnapshot(\App\Entity\Commande $order): void
    {
        foreach ($order->getCommandeArticles() as $article) {
            $this->createArticleSnapshot($article);
        }
        
        $this->entityManager->flush();
    }

    /**
     * Backfill snapshot data for existing orders that don't have it
     */
    public function backfillSnapshots(): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        // Find articles without snapshots that still have valid plat references
        $articles = $qb->select('ca')
            ->from(CommandeArticle::class, 'ca')
            ->leftJoin('ca.plat', 'p')
            ->where('ca.nomOriginal IS NULL')
            ->andWhere('p.id IS NOT NULL')
            ->getQuery()
            ->getResult();

        $count = 0;
        foreach ($articles as $article) {
            $this->createArticleSnapshot($article);
            $count++;
            
            // Flush in batches to avoid memory issues
            if ($count % 100 === 0) {
                $this->entityManager->flush();
            }
        }
        
        $this->entityManager->flush();
        
        return $count;
    }

    /**
     * Get statistics about order history preservation
     */
    public function getHistoryStats(): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        // Total articles
        $totalArticles = $qb->select('COUNT(ca.id)')
            ->from(CommandeArticle::class, 'ca')
            ->getQuery()
            ->getSingleScalarResult();

        // Articles with snapshots
        $qb->resetDQLPart('select')->select('COUNT(ca.id)');
        $articlesWithSnapshots = $qb->where('ca.nomOriginal IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        // Deleted items (articles without plat)
        $qb->resetDQLPart('select')->resetDQLPart('where')
            ->select('COUNT(ca.id)')
            ->leftJoin('ca.plat', 'p')
            ->where('p.id IS NULL');
        $deletedItems = $qb->getQuery()->getSingleScalarResult();

        // Orphaned articles (no snapshot and no plat)
        $qb->resetDQLPart('select')->resetDQLPart('where')
            ->select('COUNT(ca.id)')
            ->leftJoin('ca.plat', 'p')
            ->where('p.id IS NULL')
            ->andWhere('ca.nomOriginal IS NULL');
        $orphanedArticles = $qb->getQuery()->getSingleScalarResult();

        return [
            'totalArticles' => (int) $totalArticles,
            'articlesWithSnapshots' => (int) $articlesWithSnapshots,
            'deletedItems' => (int) $deletedItems,
            'orphanedArticles' => (int) $orphanedArticles,
            'snapshotCoverage' => $totalArticles > 0 ? round(($articlesWithSnapshots / $totalArticles) * 100, 1) : 0
        ];
    }
} 