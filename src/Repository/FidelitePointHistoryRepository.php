<?php

namespace App\Repository;

use App\Entity\FidelitePointHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FidelitePointHistory>
 */
class FidelitePointHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FidelitePointHistory::class);
    }

    /**
     * Find history by client profile
     */
    public function findByClientProfile(int $clientProfileId): array
    {
        return $this->createQueryBuilder('fph')
            ->join('fph.clientProfile', 'cp')
            ->andWhere('cp.id = :clientProfileId')
            ->setParameter('clientProfileId', $clientProfileId)
            ->orderBy('fph.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find history by type
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('fph')
            ->andWhere('fph.type = :type')
            ->setParameter('type', $type)
            ->orderBy('fph.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calculate total points for a client
     */
    public function calculateTotalPointsForClient(int $clientProfileId): int
    {
        $gains = $this->createQueryBuilder('fph')
            ->select('SUM(fph.points)')
            ->join('fph.clientProfile', 'cp')
            ->andWhere('cp.id = :clientProfileId')
            ->andWhere('fph.type = :type')
            ->setParameter('clientProfileId', $clientProfileId)
            ->setParameter('type', 'gain')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        $depenses = $this->createQueryBuilder('fph')
            ->select('SUM(fph.points)')
            ->join('fph.clientProfile', 'cp')
            ->andWhere('cp.id = :clientProfileId')
            ->andWhere('fph.type = :type')
            ->setParameter('clientProfileId', $clientProfileId)
            ->setParameter('type', 'depense')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        return (int)$gains - (int)$depenses;
    }
} 