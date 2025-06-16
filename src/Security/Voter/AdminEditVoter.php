<?php

namespace App\Security\Voter;

use App\Entity\User;
use App\Service\PermissionService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Admin Edit Voter - Handles complex admin editing permissions
 * 
 * This voter handles specific admin operations like:
 * - EDIT_ADMIN_USER
 * - DELETE_ADMIN_USER
 * - VIEW_ADMIN_DETAILS
 */
class AdminEditVoter extends Voter
{
    private const SUPPORTED_ATTRIBUTES = [
        'EDIT_ADMIN_USER',
        'DELETE_ADMIN_USER',
        'VIEW_ADMIN_DETAILS',
        'MANAGE_ADMIN_PERMISSIONS'
    ];

    public function __construct(
        private PermissionService $permissionService
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, self::SUPPORTED_ATTRIBUTES) && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $currentUser = $token->getUser();
        
        // User must be authenticated
        if (!$currentUser instanceof UserInterface) {
            return false;
        }

        // User must be a User entity
        if (!$currentUser instanceof User) {
            return false;
        }

        // Subject must be a User
        if (!$subject instanceof User) {
            return false;
        }

        return match ($attribute) {
            'EDIT_ADMIN_USER' => $this->canEditUser($currentUser, $subject),
            'DELETE_ADMIN_USER' => $this->canDeleteUser($currentUser, $subject),
            'VIEW_ADMIN_DETAILS' => $this->canViewUserDetails($currentUser, $subject),
            'MANAGE_ADMIN_PERMISSIONS' => $this->canManagePermissions($currentUser, $subject),
            default => false,
        };
    }

    private function canEditUser(User $currentUser, User $targetUser): bool
    {
        // Can't edit yourself through this voter (use separate profile editing)
        if ($currentUser->getId() === $targetUser->getId()) {
            return false;
        }

        // Use the permission service which handles all the complex logic
        return $this->permissionService->hasPermission($currentUser, 'edit_admin_user', $targetUser);
    }

    private function canDeleteUser(User $currentUser, User $targetUser): bool
    {
        // Can't delete yourself
        if ($currentUser->getId() === $targetUser->getId()) {
            return false;
        }

        // Use the permission service for complex deletion logic
        return $this->permissionService->hasPermission($currentUser, 'delete_admin_user', $targetUser);
    }

    private function canViewUserDetails(User $currentUser, User $targetUser): bool
    {
        // Users can always view their own details
        if ($currentUser->getId() === $targetUser->getId()) {
            return true;
        }

        // Check if user has permission to view other admin details
        return $this->permissionService->hasPermission($currentUser, 'view_admin_details', $targetUser);
    }

    private function canManagePermissions(User $currentUser, User $targetUser): bool
    {
        // Can't manage your own permissions (security measure)
        if ($currentUser->getId() === $targetUser->getId()) {
            return false;
        }

        // Only super admins can manage permissions
        if (!in_array('ROLE_SUPER_ADMIN', $currentUser->getRoles())) {
            return false;
        }

        return $this->permissionService->hasPermission($currentUser, 'manage_permissions', $targetUser);
    }
} 