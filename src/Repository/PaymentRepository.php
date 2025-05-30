<?php

namespace App\Repository;

use App\Entity\Payment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    /**
     * Find payments by order
     */
    public function findByOrder(int $commandeId): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.commande', 'c')
            ->andWhere('c.id = :commandeId')
            ->setParameter('commandeId', $commandeId)
            ->orderBy('p.datePaiement', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find payments by status
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.statut = :status')
            ->setParameter('status', $status)
            ->orderBy('p.datePaiement', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calculate total revenue for a period
     */
    public function calculateRevenue(\DateTime $startDate, \DateTime $endDate): float
    {
        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.montant) as revenue')
            ->andWhere('p.statut = :status')
            ->andWhere('p.datePaiement BETWEEN :startDate AND :endDate')
            ->setParameter('status', 'valide')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getSingleScalarResult();

        return (float)($result ?? 0);
    }
} 