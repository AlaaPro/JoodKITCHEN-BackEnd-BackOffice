<?php

namespace App\Repository;

use App\Entity\MenuPlat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MenuPlat>
 */
class MenuPlatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MenuPlat::class);
    }

    /**
     * Find dishes by menu
     */
    public function findByMenu(int $menuId): array
    {
        return $this->createQueryBuilder('mp')
            ->join('mp.menu', 'm')
            ->andWhere('m.id = :menuId')
            ->setParameter('menuId', $menuId)
            ->orderBy('mp.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find menus containing a specific dish
     */
    public function findMenusByDish(int $platId): array
    {
        return $this->createQueryBuilder('mp')
            ->join('mp.plat', 'p')
            ->andWhere('p.id = :platId')
            ->setParameter('platId', $platId)
            ->orderBy('mp.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }
} 