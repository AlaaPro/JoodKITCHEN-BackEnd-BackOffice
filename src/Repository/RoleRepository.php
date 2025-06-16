<?php

namespace App\Repository;

use App\Entity\Role;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Role>
 */
class RoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Role::class);
    }

    /**
     * Find all active roles with their permissions
     */
    public function findAllActiveWithPermissions(): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.permissions', 'p')
            ->addSelect('p')
            ->andWhere('r.isActive = true')
            ->orderBy('r.priority', 'DESC')
            ->addOrderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find role by name
     */
    public function findByName(string $name): ?Role
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.name = :name')
            ->andWhere('r.isActive = true')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find roles by names
     */
    public function findByNames(array $names): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.name IN (:names)')
            ->andWhere('r.isActive = true')
            ->setParameter('names', $names)
            ->orderBy('r.priority', 'DESC')
            ->addOrderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find roles with specific permission
     */
    public function findRolesWithPermission(string $permissionName): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.permissions', 'p')
            ->andWhere('p.name = :permissionName')
            ->andWhere('r.isActive = true')
            ->andWhere('p.isActive = true')
            ->setParameter('permissionName', $permissionName)
            ->orderBy('r.priority', 'DESC')
            ->addOrderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get roles with permission count
     */
    public function getRolesWithPermissionCount(): array
    {
        return $this->createQueryBuilder('r')
            ->select('r.id', 'r.name', 'r.description', 'r.priority', 'COUNT(p.id) as permissionCount')
            ->leftJoin('r.permissions', 'p')
            ->andWhere('r.isActive = true')
            ->groupBy('r.id')
            ->orderBy('r.priority', 'DESC')
            ->addOrderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search roles by name or description
     */
    public function searchRoles(string $searchTerm): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.name LIKE :search OR r.description LIKE :search')
            ->andWhere('r.isActive = true')
            ->setParameter('search', '%' . $searchTerm . '%')
            ->orderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
} 