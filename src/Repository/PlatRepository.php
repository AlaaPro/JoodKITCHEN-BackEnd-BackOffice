<?php

namespace App\Repository;

use App\Entity\Plat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Plat>
 */
class PlatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Plat::class);
    }

    /**
     * Find available plats
     */
    public function findAvailablePlats(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.disponible = :disponible')
            ->setParameter('disponible', true)
            ->orderBy('p.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find plats by category
     */
    public function findByCategory(string $category): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.categorie = :category')
            ->andWhere('p.disponible = :disponible')
            ->setParameter('category', $category)
            ->setParameter('disponible', true)
            ->orderBy('p.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search plats by name
     */
    public function searchByName(string $searchTerm): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.nom LIKE :searchTerm OR p.description LIKE :searchTerm')
            ->andWhere('p.disponible = :disponible')
            ->setParameter('searchTerm', '%'.$searchTerm.'%')
            ->setParameter('disponible', true)
            ->orderBy('p.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find plats by price range
     */
    public function findByPriceRange(float $minPrice, float $maxPrice): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.prix BETWEEN :minPrice AND :maxPrice')
            ->andWhere('p.disponible = :disponible')
            ->setParameter('minPrice', $minPrice)
            ->setParameter('maxPrice', $maxPrice)
            ->setParameter('disponible', true)
            ->orderBy('p.prix', 'ASC')
            ->getQuery()
            ->getResult();
    }
} 