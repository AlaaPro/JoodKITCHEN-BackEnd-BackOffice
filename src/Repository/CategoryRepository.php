<?php

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /**
     * Find all root categories (no parent) ordered by position
     */
    public function findRootCategories(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.parent IS NULL')
            ->andWhere('c.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all categories with their subcategories in hierarchical order
     */
    public function findHierarchicalCategories(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.sousCategories', 'sc')
            ->addSelect('sc')
            ->andWhere('c.parent IS NULL')
            ->andWhere('c.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('sc.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find subcategories by parent
     */
    public function findByParent(Category $parent): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.parent = :parent')
            ->andWhere('c.actif = :actif')
            ->setParameter('parent', $parent)
            ->setParameter('actif', true)
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find categories with dish count
     */
    public function findCategoriesWithDishCount(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.plats', 'p')
            ->addSelect('COUNT(p.id) as dishCount')
            ->andWhere('c.actif = :actif')
            ->setParameter('actif', true)
            ->groupBy('c.id')
            ->orderBy('c.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search categories by name
     */
    public function searchByName(string $searchTerm): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.nom LIKE :searchTerm OR c.description LIKE :searchTerm')
            ->andWhere('c.actif = :actif')
            ->setParameter('searchTerm', '%'.$searchTerm.'%')
            ->setParameter('actif', true)
            ->orderBy('c.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find visible categories for frontend
     */
    public function findVisibleCategories(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.actif = :actif')
            ->andWhere('c.visible = :visible')
            ->setParameter('actif', true)
            ->setParameter('visible', true)
            ->orderBy('c.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find popular categories (most dishes)
     */
    public function findPopularCategories(int $limit = 5): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.plats', 'p')
            ->andWhere('c.actif = :actif')
            ->andWhere('p.disponible = :disponible')
            ->setParameter('actif', true)
            ->setParameter('disponible', true)
            ->groupBy('c.id')
            ->orderBy('COUNT(p.id)', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Update category positions
     */
    public function updatePositions(array $categoryPositions): void
    {
        $em = $this->getEntityManager();
        
        foreach ($categoryPositions as $categoryId => $position) {
            $category = $this->find($categoryId);
            if ($category) {
                $category->setPosition($position);
                $em->persist($category);
            }
        }
        
        $em->flush();
    }

    /**
     * Find next available position for new category
     */
    public function findNextPosition(?Category $parent = null): int
    {
        $qb = $this->createQueryBuilder('c')
            ->select('MAX(c.position)')
            ->andWhere('c.actif = :actif')
            ->setParameter('actif', true);

        if ($parent) {
            $qb->andWhere('c.parent = :parent')
               ->setParameter('parent', $parent);
        } else {
            $qb->andWhere('c.parent IS NULL');
        }

        $maxPosition = $qb->getQuery()->getSingleScalarResult();
        
        return ($maxPosition ?: 0) + 1;
    }
} 