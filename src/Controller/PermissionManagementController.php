<?php

namespace App\Controller;

use App\Entity\Permission;
use App\Entity\Role;
use App\Entity\User;
use App\Repository\PermissionRepository;
use App\Repository\RoleRepository;
use App\Service\PermissionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Permission Management Controller
 * 
 * Provides enterprise-grade permission management APIs:
 * - Permission CRUD operations
 * - Role management with permission assignment
 * - Bulk permission operations
 * - Permission matrix visualization
 */
#[Route('/api/admin/permission-management')]
class PermissionManagementController extends AbstractController
{
    public function __construct(
        private PermissionService $permissionService,
        private EntityManagerInterface $em,
        private PermissionRepository $permissionRepository,
        private RoleRepository $roleRepository
    ) {}

    /**
     * Get all permissions grouped by category
     */
    #[Route('/permissions', name: 'api_permission_management_permissions', methods: ['GET'])]
    #[IsGranted('PERM_MANAGE_PERMISSIONS')]
    public function getPermissions(): JsonResponse
    {
        $permissions = $this->permissionRepository->getPermissionsGroupedByCategory();
        
        return new JsonResponse([
            'success' => true,
            'permissions' => $permissions,
            'total_count' => array_sum(array_map('count', $permissions))
        ]);
    }

    /**
     * Create a new permission
     */
    #[Route('/permissions', name: 'api_permission_management_create_permission', methods: ['POST'])]
    #[IsGranted('PERM_MANAGE_PERMISSIONS')]
    public function createPermission(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return new JsonResponse(['error' => 'Invalid JSON data'], 400);
        }

        $permission = new Permission();
        $permission->setName($data['name'] ?? '');
        $permission->setDescription($data['description'] ?? '');
        $permission->setCategory($data['category'] ?? 'general');
        $permission->setPriority($data['priority'] ?? 0);
        $permission->setIsActive($data['is_active'] ?? true);

        $errors = $validator->validate($permission);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
            }
            return new JsonResponse(['error' => 'Validation failed', 'details' => $errorMessages], 400);
        }

        $this->em->persist($permission);
        $this->em->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Permission created successfully',
            'permission' => [
                'id' => $permission->getId(),
                'name' => $permission->getName(),
                'description' => $permission->getDescription(),
                'category' => $permission->getCategory(),
                'priority' => $permission->getPriority(),
                'is_active' => $permission->isActive()
            ]
        ], 201);
    }

    /**
     * Get all roles with their permissions
     */
    #[Route('/roles', name: 'api_permission_management_roles', methods: ['GET'])]
    #[IsGranted('PERM_MANAGE_ROLES')]
    public function getRoles(): JsonResponse
    {
        $roles = $this->roleRepository->findAllActiveWithPermissions();
        
        $rolesData = [];
        foreach ($roles as $role) {
            $rolesData[] = [
                'id' => $role->getId(),
                'name' => $role->getName(),
                'description' => $role->getDescription(),
                'priority' => $role->getPriority(),
                'is_active' => $role->isActive(),
                'permissions' => array_map(fn($p) => [
                    'id' => $p->getId(),
                    'name' => $p->getName(),
                    'category' => $p->getCategory()
                ], $role->getPermissions()->toArray()),
                'permission_count' => $role->getPermissions()->count()
            ];
        }

        return new JsonResponse([
            'success' => true,
            'roles' => $rolesData,
            'total_count' => count($rolesData)
        ]);
    }

    /**
     * Create a new role
     */
    #[Route('/roles', name: 'api_permission_management_create_role', methods: ['POST'])]
    #[IsGranted('PERM_MANAGE_ROLES')]
    public function createRole(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return new JsonResponse(['error' => 'Invalid JSON data'], 400);
        }

        $role = new Role();
        $role->setName($data['name'] ?? '');
        $role->setDescription($data['description'] ?? '');
        $role->setPriority($data['priority'] ?? 0);
        $role->setIsActive($data['is_active'] ?? true);

        // Add permissions if provided
        if (!empty($data['permission_ids']) && is_array($data['permission_ids'])) {
            $permissions = $this->permissionRepository->findBy(['id' => $data['permission_ids']]);
            foreach ($permissions as $permission) {
                $role->addPermission($permission);
            }
        }

        $errors = $validator->validate($role);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
            }
            return new JsonResponse(['error' => 'Validation failed', 'details' => $errorMessages], 400);
        }

        $this->em->persist($role);
        $this->em->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Role created successfully',
            'role' => [
                'id' => $role->getId(),
                'name' => $role->getName(),
                'description' => $role->getDescription(),
                'priority' => $role->getPriority(),
                'is_active' => $role->isActive(),
                'permission_count' => $role->getPermissions()->count()
            ]
        ], 201);
    }

    /**
     * Assign permissions to a user's admin profile
     */
    #[Route('/users/{id}/permissions', name: 'api_permission_management_assign_permissions', methods: ['POST'])]
    #[IsGranted('PERM_MANAGE_USER_PERMISSIONS')]
    public function assignUserPermissions(int $id, Request $request): JsonResponse
    {
        $user = $this->em->getRepository(User::class)->find($id);
        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], 404);
        }

        // Check if current user can manage target user's permissions
        $this->denyAccessUnlessGranted('MANAGE_ADMIN_PERMISSIONS', $user);

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(['error' => 'Invalid JSON data'], 400);
        }

        $adminProfile = $user->getAdminProfile();
        if (!$adminProfile) {
            return new JsonResponse(['error' => 'User does not have an admin profile'], 400);
        }

        // Clear existing permissions
        foreach ($adminProfile->getPermissions() as $permission) {
            $adminProfile->removePermission($permission);
        }

        // Add new permissions
        if (!empty($data['permission_ids']) && is_array($data['permission_ids'])) {
            $permissions = $this->permissionRepository->findBy(['id' => $data['permission_ids']]);
            foreach ($permissions as $permission) {
                $adminProfile->addPermission($permission);
            }
        }

        // Clear existing roles
        foreach ($adminProfile->getRoles() as $role) {
            $adminProfile->removeRole($role);
        }

        // Add new roles
        if (!empty($data['role_ids']) && is_array($data['role_ids'])) {
            $roles = $this->roleRepository->findBy(['id' => $data['role_ids']]);
            foreach ($roles as $role) {
                $adminProfile->addRole($role);
            }
        }

        $this->em->flush();

        // Invalidate permission cache
        $this->permissionService->invalidateUserCache($user);

        return new JsonResponse([
            'success' => true,
            'message' => 'Permissions updated successfully',
            'user_id' => $user->getId(),
            'direct_permissions' => count($adminProfile->getPermissions()),
            'roles' => count($adminProfile->getRoles()),
            'total_permissions' => count($adminProfile->getAllPermissions())
        ]);
    }

    /**
     * Get permission matrix for visualization
     */
    #[Route('/matrix', name: 'api_permission_management_matrix', methods: ['GET'])]
    #[IsGranted('PERM_VIEW_PERMISSION_MATRIX')]
    public function getPermissionMatrix(): JsonResponse
    {
        // Get all admin users
        $users = $this->em->getRepository(User::class)
            ->createQueryBuilder('u')
            ->leftJoin('u.adminProfile', 'ap')
            ->where('u.roles LIKE :admin_role OR u.roles LIKE :super_admin_role')
            ->setParameter('admin_role', '%ROLE_ADMIN%')
            ->setParameter('super_admin_role', '%ROLE_SUPER_ADMIN%')
            ->getQuery()
            ->getResult();

        $permissions = $this->permissionRepository->findAllActiveOrderedByCategory();
        $roles = $this->roleRepository->findAllActiveWithPermissions();

        $matrix = [];
        foreach ($users as $user) {
            $adminProfile = $user->getAdminProfile();
            $userPermissions = $adminProfile ? $adminProfile->getAllPermissionNames() : [];

            $matrix[] = [
                'user' => [
                    'id' => $user->getId(),
                    'name' => $user->getPrenom() . ' ' . $user->getNom(),
                    'email' => $user->getEmail(),
                    'roles' => $user->getRoles()
                ],
                'permissions' => $userPermissions,
                'permission_sources' => [
                    'direct' => $adminProfile ? count($adminProfile->getPermissions()) : 0,
                    'from_roles' => $adminProfile ? count($adminProfile->getRoles()) : 0,
                    'legacy' => $adminProfile ? count($adminProfile->getPermissionsAvancees() ?? []) : 0
                ]
            ];
        }

        return new JsonResponse([
            'success' => true,
            'matrix' => $matrix,
            'available_permissions' => array_map(fn($p) => [
                'id' => $p->getId(),
                'name' => $p->getName(),
                'category' => $p->getCategory()
            ], $permissions),
            'available_roles' => array_map(fn($r) => [
                'id' => $r->getId(),
                'name' => $r->getName(),
                'permission_count' => $r->getPermissions()->count()
            ], $roles),
            'summary' => [
                'total_users' => count($users),
                'total_permissions' => count($permissions),
                'total_roles' => count($roles)
            ]
        ]);
    }

    /**
     * Bulk update permissions for multiple users
     */
    #[Route('/bulk-update', name: 'api_permission_management_bulk_update', methods: ['POST'])]
    #[IsGranted('PERM_BULK_MANAGE_PERMISSIONS')]
    public function bulkUpdatePermissions(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data || !isset($data['operations']) || !is_array($data['operations'])) {
            return new JsonResponse(['error' => 'Invalid request format'], 400);
        }

        $results = [];
        $this->em->beginTransaction();

        try {
            foreach ($data['operations'] as $operation) {
                $userId = $operation['user_id'] ?? null;
                $action = $operation['action'] ?? null; // 'add_permission', 'remove_permission', 'add_role', 'remove_role'
                $targetId = $operation['target_id'] ?? null;

                if (!$userId || !$action || !$targetId) {
                    $results[] = ['user_id' => $userId, 'status' => 'error', 'message' => 'Invalid operation data'];
                    continue;
                }

                $user = $this->em->getRepository(User::class)->find($userId);
                if (!$user || !$user->getAdminProfile()) {
                    $results[] = ['user_id' => $userId, 'status' => 'error', 'message' => 'User or admin profile not found'];
                    continue;
                }

                // Check permissions for each user
                if (!$this->isGranted('MANAGE_ADMIN_PERMISSIONS', $user)) {
                    $results[] = ['user_id' => $userId, 'status' => 'error', 'message' => 'Permission denied'];
                    continue;
                }

                $adminProfile = $user->getAdminProfile();
                $success = false;

                switch ($action) {
                    case 'add_permission':
                        $permission = $this->permissionRepository->find($targetId);
                        if ($permission) {
                            $adminProfile->addPermission($permission);
                            $success = true;
                        }
                        break;
                    
                    case 'remove_permission':
                        $permission = $this->permissionRepository->find($targetId);
                        if ($permission) {
                            $adminProfile->removePermission($permission);
                            $success = true;
                        }
                        break;
                    
                    case 'add_role':
                        $role = $this->roleRepository->find($targetId);
                        if ($role) {
                            $adminProfile->addRole($role);
                            $success = true;
                        }
                        break;
                    
                    case 'remove_role':
                        $role = $this->roleRepository->find($targetId);
                        if ($role) {
                            $adminProfile->removeRole($role);
                            $success = true;
                        }
                        break;
                }

                if ($success) {
                    $this->permissionService->invalidateUserCache($user);
                    $results[] = ['user_id' => $userId, 'status' => 'success', 'action' => $action];
                } else {
                    $results[] = ['user_id' => $userId, 'status' => 'error', 'message' => 'Target not found or action failed'];
                }
            }

            $this->em->flush();
            $this->em->commit();

            return new JsonResponse([
                'success' => true,
                'message' => 'Bulk update completed',
                'results' => $results,
                'processed' => count($results),
                'successful' => count(array_filter($results, fn($r) => $r['status'] === 'success'))
            ]);

        } catch (\Exception $e) {
            $this->em->rollback();
            return new JsonResponse([
                'error' => 'Bulk update failed',
                'message' => $e->getMessage(),
                'partial_results' => $results
            ], 500);
        }
    }
} 