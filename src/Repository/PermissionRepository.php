<?php

namespace App\Repository;

use App\Entity\Permission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Permission>
 */
class PermissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Permission::class);
    }

    /**
     * Find permissions by category
     */
    public function findByCategory(string $category): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.category = :category')
            ->andWhere('p.isActive = true')
            ->setParameter('category', $category)
            ->orderBy('p.priority', 'DESC')
            ->addOrderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find active permissions ordered by category and priority
     */
    public function findAllActiveOrderedByCategory(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = true')
            ->orderBy('p.category', 'ASC')
            ->addOrderBy('p.priority', 'DESC')
            ->addOrderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find permissions by names
     */
    public function findByNames(array $names): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.name IN (:names)')
            ->andWhere('p.isActive = true')
            ->setParameter('names', $names)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get permissions grouped by category
     */
    public function getPermissionsGroupedByCategory(): array
    {
        $permissions = $this->findAllActiveOrderedByCategory();
        $grouped = [];
        
        foreach ($permissions as $permission) {
            $category = $permission->getCategory();
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = $permission;
        }
        
        return $grouped;
    }

    /**
     * Search permissions by name or description
     */
    public function searchPermissions(string $searchTerm): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.name LIKE :search OR p.description LIKE :search')
            ->andWhere('p.isActive = true')
            ->setParameter('search', '%' . $searchTerm . '%')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
} 