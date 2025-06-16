<?php

namespace App\Security\Voter;

use App\Entity\User;
use App\Service\PermissionService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Permission Voter - Handles all PERM_ prefixed attributes
 * 
 * This voter integrates with our PermissionService to provide
 * declarative permission checking via @IsGranted annotations
 */
class PermissionVoter extends Voter
{
    public function __construct(
        private PermissionService $permissionService
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        // Handle both PERM_ prefixed attributes and direct permission names
        $supports = str_starts_with($attribute, 'PERM_') || $this->isPermissionName($attribute);
        
        // Debug logging
        error_log("PermissionVoter::supports - Attribute: $attribute, Supports: " . ($supports ? 'YES' : 'NO'));
        
        return $supports;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        
        // User must be authenticated
        if (!$user instanceof UserInterface) {
            return false;
        }

        // User must be a User entity (not just UserInterface)
        if (!$user instanceof User) {
            return false;
        }

        // Convert permission name to standard format
        if (str_starts_with($attribute, 'PERM_')) {
            // Convert PERM_MANAGE_ADMINS to manage_admins
            $permission = strtolower(str_replace('PERM_', '', $attribute));
        } else {
            // Use permission name as-is (e.g., manage_admins)
            $permission = $attribute;
        }

        return $this->permissionService->hasPermission($user, $permission, $subject);
    }

    /**
     * Check if the attribute looks like a permission name
     */
    private function isPermissionName(string $attribute): bool
    {
        // List of known permission patterns
        $permissionPatterns = [
            'manage_', 'view_', 'edit_', 'delete_', 'create_',
            'dashboard', 'users', 'orders', 'kitchen', 'customers',
            'export_', 'system'
        ];

        foreach ($permissionPatterns as $pattern) {
            if (str_starts_with($attribute, $pattern) || str_contains($attribute, $pattern)) {
                return true;
            }
        }

        return false;
    }
} 