<?php

namespace App\Repository;

use App\Entity\CommandeArticle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommandeArticle>
 */
class CommandeArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommandeArticle::class);
    }

    /**
     * Find articles by order
     */
    public function findByOrder(int $commandeId): array
    {
        return $this->createQueryBuilder('ca')
            ->join('ca.commande', 'c')
            ->andWhere('c.id = :commandeId')
            ->setParameter('commandeId', $commandeId)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find most popular plats
     */
    public function findMostPopularPlats(int $limit = 10): array
    {
        return $this->createQueryBuilder('ca')
            ->select('p.nom, p.id, SUM(ca.quantite) as totalQuantity')
            ->join('ca.plat', 'p')
            ->groupBy('p.id')
            ->orderBy('totalQuantity', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Calculate total revenue for a dish
     */
    public function calculateDishRevenue(int $platId): float
    {
        $result = $this->createQueryBuilder('ca')
            ->select('SUM(ca.quantite * ca.prixUnitaire) as revenue')
            ->join('ca.plat', 'p')
            ->andWhere('p.id = :platId')
            ->setParameter('platId', $platId)
            ->getQuery()
            ->getSingleScalarResult();

        return (float)($result ?? 0);
    }
} 