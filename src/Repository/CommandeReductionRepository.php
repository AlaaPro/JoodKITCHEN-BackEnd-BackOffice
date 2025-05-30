<?php

namespace App\Repository;

use App\Entity\CommandeReduction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommandeReduction>
 */
class CommandeReductionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommandeReduction::class);
    }

    /**
     * Find reductions by order
     */
    public function findByOrder(int $commandeId): array
    {
        return $this->createQueryBuilder('cr')
            ->join('cr.commande', 'c')
            ->andWhere('c.id = :commandeId')
            ->setParameter('commandeId', $commandeId)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find reductions by type
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('cr')
            ->andWhere('cr.type = :type')
            ->setParameter('type', $type)
            ->getQuery()
            ->getResult();
    }

    /**
     * Calculate total reductions for an order
     */
    public function calculateTotalReductionsForOrder(int $commandeId): float
    {
        $result = $this->createQueryBuilder('cr')
            ->select('SUM(cr.valeur) as totalReductions')
            ->join('cr.commande', 'c')
            ->andWhere('c.id = :commandeId')
            ->setParameter('commandeId', $commandeId)
            ->getQuery()
            ->getSingleScalarResult();

        return (float)($result ?? 0);
    }
} 