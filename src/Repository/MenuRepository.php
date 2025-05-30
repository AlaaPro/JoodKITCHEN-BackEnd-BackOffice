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
    public function findMenuOfTheDay(\DateTime $date = null): array
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
} 