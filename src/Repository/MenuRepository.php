<?php

namespace App\Repository;

use App\Entity\Menu;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Menu>
 */
class MenuRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Menu::class);
    }

    /**
     * Find active menus
     */
    public function findActiveMenus(): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('m.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find menus by type
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.type = :type')
            ->andWhere('m.actif = :actif')
            ->setParameter('type', $type)
            ->setParameter('actif', true)
            ->orderBy('m.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find menu of the day
     */
    public function findMenuOfTheDay(?\DateTime $date = null): array
    {
        if (!$date) {
            $date = new \DateTime();
        }

        return $this->createQueryBuilder('m')
            ->andWhere('m.type = :type')
            ->andWhere('m.date = :date')
            ->andWhere('m.actif = :actif')
            ->setParameter('type', 'menu_du_jour')
            ->setParameter('date', $date->format('Y-m-d'))
            ->setParameter('actif', true)
            ->orderBy('m.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find today's menus (POS compatible)
     */
    public function findTodaysMenus(?string $date = null): array
    {
        if (!$date) {
            $date = date('Y-m-d');
        }

        return $this->createQueryBuilder('m')
            ->andWhere('m.type = :type')
            ->andWhere('m.date = :date')
            ->andWhere('m.actif = :actif')
            ->setParameter('type', 'menu_du_jour')
            ->setParameter('date', $date)
            ->setParameter('actif', true)
            ->orderBy('m.tag', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get menu statistics
     */
    public function getMenuStats(): array
    {
        $today = date('Y-m-d');
        
        // Total menus count
        $totalMenus = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.actif = :actif')
            ->setParameter('actif', true)
            ->getQuery()
            ->getSingleScalarResult();

        // Normal menus count
        $normalMenus = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.type = :type')
            ->andWhere('m.actif = :actif')
            ->setParameter('type', 'normal')
            ->setParameter('actif', true)
            ->getQuery()
            ->getSingleScalarResult();

        // Daily menus count
        $dailyMenus = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.type = :type')
            ->andWhere('m.actif = :actif')
            ->setParameter('type', 'menu_du_jour')
            ->setParameter('actif', true)
            ->getQuery()
            ->getSingleScalarResult();

        // Today's menus count
        $todayMenus = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.type = :type')
            ->andWhere('m.date = :date')
            ->andWhere('m.actif = :actif')
            ->setParameter('type', 'menu_du_jour')
            ->setParameter('date', $today)
            ->setParameter('actif', true)
            ->getQuery()
            ->getSingleScalarResult();

        // Average price
        $avgPrice = $this->createQueryBuilder('m')
            ->select('AVG(m.prix)')
            ->andWhere('m.actif = :actif')
            ->setParameter('actif', true)
            ->getQuery()
            ->getSingleScalarResult() ?: 0;

        return [
            'total' => (int)$totalMenus,
            'normal' => (int)$normalMenus,
            'daily' => (int)$dailyMenus,
            'today' => (int)$todayMenus,
            'avg_price' => round((float)$avgPrice, 2)
        ];
    }
} 