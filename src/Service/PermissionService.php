<?php

namespace App\Service;

use App\Entity\AdminProfile;
use App\Entity\Permission;
use App\Entity\Role;
use App\Entity\User;
use App\Repository\PermissionRepository;
use App\Repository\RoleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Enterprise-grade Permission Service
 * 
 * This service handles all permission checking with:
 * - Multi-layer caching for performance
 * - Permission inheritance (roles -> permissions)
 * - Context-aware permission checking
 * - Backward compatibility with JSON permissions
 */
class PermissionService
{
    private const CACHE_TTL = 3600; // 1 hour
    private const CACHE_PREFIX = 'permission_';

    public function __construct(
        private EntityManagerInterface $em,
        private PermissionRepository $permissionRepository,
        private RoleRepository $roleRepository,
        private CacheItemPoolInterface $cache,
        private LoggerInterface $logger
    ) {}

    /**
     * Main permission checking method
     */
    public function hasPermission(User $user, string $permission, mixed $subject = null): bool
    {
        try {
            $cacheKey = $this->generateCacheKey($user->getId(), $permission, $subject);
            
            $cacheItem = $this->cache->getItem($cacheKey);
            if ($cacheItem->isHit()) {
                return $cacheItem->get();
            }

            $hasPermission = $this->checkPermissionInternal($user, $permission, $subject);
            
            $cacheItem->set($hasPermission);
            $cacheItem->expiresAfter(self::CACHE_TTL);
            $this->cache->save($cacheItem);

            return $hasPermission;

        } catch (\Exception $e) {
            $this->logger->error('Permission check failed', [
                'user_id' => $user->getId(),
                'permission' => $permission,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Internal permission checking logic
     */
    private function checkPermissionInternal(User $user, string $permission, mixed $subject): bool
    {
        // Debug logging
        $this->logger->info('Checking permission', [
            'user_id' => $user->getId(),
            'permission' => $permission,
            'user_roles' => $user->getRoles()
        ]);

        // 1. Check Symfony roles first (ROLE_SUPER_ADMIN gets everything)
        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles())) {
            $this->logger->info('Permission granted via ROLE_SUPER_ADMIN', [
                'user_id' => $user->getId(),
                'permission' => $permission
            ]);
            return true;
        }

        $adminProfile = $user->getAdminProfile();
        if (!$adminProfile) {
            $this->logger->warning('No admin profile found', [
                'user_id' => $user->getId(),
                'permission' => $permission
            ]);
            return false;
        }

        // 2. Check new normalized permissions
        if ($this->checkNormalizedPermissions($adminProfile, $permission)) {
            $this->logger->info('Permission granted via normalized permissions', [
                'user_id' => $user->getId(),
                'permission' => $permission
            ]);
            return true;
        }

        // 3. Backward compatibility: Check JSON permissions
        if ($this->checkLegacyPermissions($adminProfile, $permission)) {
            $this->logger->info('Permission granted via legacy JSON permissions', [
                'user_id' => $user->getId(),
                'permission' => $permission,
                'legacy_permissions' => $adminProfile->getPermissionsAvancees()
            ]);
            return true;
        }

        // 4. Context-specific checks
        if ($subject && $this->checkContextualPermissions($user, $permission, $subject)) {
            $this->logger->info('Permission granted via contextual check', [
                'user_id' => $user->getId(),
                'permission' => $permission
            ]);
            return true;
        }

        $this->logger->warning('Permission denied', [
            'user_id' => $user->getId(),
            'permission' => $permission,
            'legacy_permissions' => $adminProfile->getPermissionsAvancees()
        ]);

        return false;
    }

    /**
     * Check normalized permissions (new system)
     */
    private function checkNormalizedPermissions(AdminProfile $adminProfile, string $permission): bool
    {
        // Check if admin profile has the permission through normalized relationships
        return $adminProfile->hasPermissionByName($permission);
    }

    /**
     * Check legacy JSON permissions (backward compatibility)
     */
    private function checkLegacyPermissions(AdminProfile $adminProfile, string $permission): bool
    {
        $legacyPermissions = $adminProfile->getPermissionsAvancees() ?? [];
        return in_array($permission, $legacyPermissions);
    }

    /**
     * Context-specific permission checking (e.g., can user A edit user B?)
     */
    private function checkContextualPermissions(User $currentUser, string $permission, mixed $subject): bool
    {
        // Handle admin editing permissions
        if ($permission === 'edit_admin_user' && $subject instanceof User) {
            return $this->canEditUser($currentUser, $subject);
        }

        if ($permission === 'delete_admin_user' && $subject instanceof User) {
            return $this->canDeleteUser($currentUser, $subject);
        }

        return false;
    }

    /**
     * Complex logic for admin editing permissions
     */
    private function canEditUser(User $currentUser, User $targetUser): bool
    {
        $currentProfile = $currentUser->getAdminProfile();
        if (!$currentProfile) {
            return false;
        }

        $targetRoles = $targetUser->getRoles();

        // Super admin can edit everyone
        if (in_array('ROLE_SUPER_ADMIN', $currentUser->getRoles())) {
            return true;
        }

        // Check if target user is SUPER_ADMIN
        if (in_array('ROLE_SUPER_ADMIN', $targetRoles)) {
            return $this->hasPermission($currentUser, 'edit_super_admin');
        }
        
        // Check if target user is ADMIN
        if (in_array('ROLE_ADMIN', $targetRoles)) {
            return $this->hasPermission($currentUser, 'edit_admin');
        }

        return false;
    }

    /**
     * Check if user can delete another user
     */
    private function canDeleteUser(User $currentUser, User $targetUser): bool
    {
        // More restrictive than edit - only super admins can delete other admins
        if (!in_array('ROLE_SUPER_ADMIN', $currentUser->getRoles())) {
            return false;
        }

        // Can't delete yourself
        if ($currentUser->getId() === $targetUser->getId()) {
            return false;
        }

        return $this->hasPermission($currentUser, 'delete_admin');
    }

    /**
     * Get all permissions for a user (flattened list)
     */
    public function getUserPermissions(User $user): array
    {
        $cacheKey = self::CACHE_PREFIX . 'user_permissions_' . $user->getId();
        
        $cacheItem = $this->cache->getItem($cacheKey);
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        $permissions = [];

        $adminProfile = $user->getAdminProfile();
        if ($adminProfile) {
            // Get normalized permissions
            $normalizedPermissions = $adminProfile->getAllPermissionNames();
            $permissions = array_merge($permissions, $normalizedPermissions);

            // Add legacy permissions for backward compatibility
            $legacyPermissions = $adminProfile->getPermissionsAvancees() ?? [];
            $permissions = array_merge($permissions, $legacyPermissions);
        }

        // Add role-based permissions
        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles())) {
            $permissions = array_merge($permissions, $this->getAllSystemPermissions());
        }

        $permissions = array_unique($permissions);

        $cacheItem->set($permissions);
        $cacheItem->expiresAfter(self::CACHE_TTL);
        $this->cache->save($cacheItem);

        return $permissions;
    }

    /**
     * Get contextual permissions for a user against a specific subject
     */
    public function getUserContextualPermissions(User $user, mixed $subject = null): array
    {
        $permissions = [
            'can_view' => true, // Basic view permission
        ];

        if ($subject instanceof User) {
            $permissions['can_edit'] = $this->hasPermission($user, 'edit_admin_user', $subject);
            $permissions['can_delete'] = $this->hasPermission($user, 'delete_admin_user', $subject);
        }

        return $permissions;
    }

    /**
     * Invalidate user permission cache
     */
    public function invalidateUserCache(User $user): void
    {
        try {
            // Clear user-specific caches
            $this->cache->deleteItem(self::CACHE_PREFIX . 'user_permissions_' . $user->getId());
            
            // Clear contextual permission caches (they contain user ID)
            $this->cache->clear(); // In production, you'd want more targeted clearing
            
            $this->logger->info('User permission cache invalidated', ['user_id' => $user->getId()]);
        } catch (\Exception $e) {
            $this->logger->warning('Failed to invalidate user cache', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get all available system permissions
     */
    private function getAllSystemPermissions(): array
    {
        return [
            'dashboard', 'users', 'orders', 'kitchen', 'menu', 'inventory',
            'customers', 'reports', 'settings', 'system', 'support',
            'edit_admin', 'edit_super_admin', 'delete_admin', 'manage_permissions',
            'manage_roles', 'view_analytics', 'manage_system'
        ];
    }

    /**
     * Generate cache key for permission check
     */
    private function generateCacheKey(int $userId, string $permission, mixed $subject): string
    {
        $subjectKey = '';
        if ($subject instanceof User) {
            $subjectKey = '_user_' . $subject->getId();
        } elseif (is_object($subject)) {
            $subjectKey = '_' . get_class($subject) . '_' . ($subject->getId() ?? 'unknown');
        }

        return self::CACHE_PREFIX . $userId . '_' . $permission . $subjectKey;
    }

    /**
     * Check if permission system is healthy
     */
    public function healthCheck(): array
    {
        try {
            $permissionCount = $this->permissionRepository->count([]);
            $roleCount = $this->roleRepository->count([]);
            
            return [
                'status' => 'healthy',
                'permissions_count' => $permissionCount,
                'roles_count' => $roleCount,
                'cache_enabled' => true
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }
} 