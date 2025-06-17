<?php

namespace App\Repository;

use App\Entity\AbonnementSelection;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AbonnementSelection>
 */
class AbonnementSelectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AbonnementSelection::class);
    }

    /**
     * Find selections by subscription
     */
    public function findByAbonnement(int $abonnementId): array
    {
        return $this->createQueryBuilder('abs')
            ->join('abs.abonnement', 'a')
            ->andWhere('a.id = :abonnementId')
            ->setParameter('abonnementId', $abonnementId)
            ->orderBy('abs.dateRepas', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find selections for a specific week
     */
    public function findByWeek(int $abonnementId, \DateTimeInterface $weekStart): array
    {
        $weekEnd = (new \DateTime())->setTimestamp($weekStart->getTimestamp());
        $weekEnd->add(new \DateInterval('P6D')); // Add 6 days to get the end of week

        return $this->createQueryBuilder('abs')
            ->join('abs.abonnement', 'a')
            ->andWhere('a.id = :abonnementId')
            ->andWhere('abs.dateRepas BETWEEN :weekStart AND :weekEnd')
            ->setParameter('abonnementId', $abonnementId)
            ->setParameter('weekStart', $weekStart)
            ->setParameter('weekEnd', $weekEnd)
            ->orderBy('abs.dateRepas', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find selections for a specific date
     */
    public function findByDate(\DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('abs')
            ->andWhere('abs.dateRepas = :date')
            ->setParameter('date', $date)
            ->orderBy('abs.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count selections by cuisine type for a week
     */
    public function countByCuisineTypeForWeek(\DateTimeInterface $weekStart): array
    {
        $weekEnd = (new \DateTime())->setTimestamp($weekStart->getTimestamp());
        $weekEnd->add(new \DateInterval('P6D'));

        return $this->createQueryBuilder('abs')
            ->select('abs.cuisineType, COUNT(abs.id) as count')
            ->andWhere('abs.dateRepas BETWEEN :weekStart AND :weekEnd')
            ->andWhere('abs.cuisineType IS NOT NULL')
            ->setParameter('weekStart', $weekStart)
            ->setParameter('weekEnd', $weekEnd)
            ->groupBy('abs.cuisineType')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Find incomplete weeks for a subscription
     */
    public function findIncompleteWeeks(int $abonnementId): array
    {
        // A complete week should have 5 selections (Monday to Friday)
        return $this->createQueryBuilder('abs')
            ->select('YEAR(abs.dateRepas) as year, WEEK(abs.dateRepas) as week, COUNT(abs.id) as count')
            ->join('abs.abonnement', 'a')
            ->andWhere('a.id = :abonnementId')
            ->andWhere('abs.statut = :statut')
            ->setParameter('abonnementId', $abonnementId)
            ->setParameter('statut', 'selectionne')
            ->groupBy('year, week')
            ->having('count < 5')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Find selections ready for preparation (for kitchen)
     */
    public function findForPreparation(\DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('abs')
            ->join('abs.abonnement', 'a')
            ->leftJoin('abs.menu', 'm')
            ->leftJoin('abs.plat', 'p')
            ->andWhere('abs.dateRepas = :date')
            ->andWhere('abs.statut = :statut')
            ->andWhere('a.statut = :abonnementStatut')
            ->setParameter('date', $date)
            ->setParameter('statut', 'confirme')
            ->setParameter('abonnementStatut', 'actif')
            ->orderBy('abs.cuisineType', 'ASC')
            ->addOrderBy('abs.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
} 