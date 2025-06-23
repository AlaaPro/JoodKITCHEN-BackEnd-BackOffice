<?php

namespace App\DataFixtures;

use App\Entity\Permission;
use App\Entity\Role;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Permission System Fixtures
 * 
 * Seeds the database with initial permissions and roles for the
 * JoodKitchen admin system. This provides a complete permission
 * structure that can be extended as needed.
 */
class PermissionFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Create permissions first
        $permissions = $this->createPermissions($manager);
        
        // Create roles and assign permissions
        $this->createRoles($manager, $permissions);
        
        $manager->flush();
    }

    private function createPermissions(ObjectManager $manager): array
    {
        $permissionsData = [
            // Dashboard Permissions
            ['name' => 'view_dashboard', 'description' => 'View admin dashboard', 'category' => 'dashboard', 'priority' => 100],
            ['name' => 'view_analytics', 'description' => 'View analytics and reports', 'category' => 'dashboard', 'priority' => 90],
            ['name' => 'export_data', 'description' => 'Export data and reports', 'category' => 'dashboard', 'priority' => 80],

            // User Management Permissions
            ['name' => 'view_admins', 'description' => 'View admin users list', 'category' => 'users', 'priority' => 100],
            ['name' => 'manage_admins', 'description' => 'Create and manage admin users', 'category' => 'users', 'priority' => 90],
            ['name' => 'edit_admin', 'description' => 'Edit regular admin users', 'category' => 'users', 'priority' => 80],
            ['name' => 'edit_super_admin', 'description' => 'Edit super admin users', 'category' => 'users', 'priority' => 70],
            ['name' => 'delete_admin', 'description' => 'Delete admin users', 'category' => 'users', 'priority' => 60],
            ['name' => 'view_user_details', 'description' => 'View detailed user information', 'category' => 'users', 'priority' => 50],

            // Permission Management
            ['name' => 'manage_permissions', 'description' => 'Manage system permissions', 'category' => 'permissions', 'priority' => 100],
            ['name' => 'view_permissions', 'description' => 'View available permissions', 'category' => 'permissions', 'priority' => 90],
            ['name' => 'manage_roles', 'description' => 'Create and manage roles', 'category' => 'permissions', 'priority' => 80],
            ['name' => 'view_roles', 'description' => 'View available roles', 'category' => 'permissions', 'priority' => 70],
            ['name' => 'view_permission_matrix', 'description' => 'View permission assignment matrix', 'category' => 'permissions', 'priority' => 60],
            ['name' => 'manage_user_permissions', 'description' => 'Assign permissions to users', 'category' => 'permissions', 'priority' => 50],
            ['name' => 'bulk_manage_permissions', 'description' => 'Bulk update permissions', 'category' => 'permissions', 'priority' => 40],
            ['name' => 'view_user_permissions', 'description' => 'View user-specific permissions', 'category' => 'permissions', 'priority' => 30],

            // Orders Management
            ['name' => 'view_orders', 'description' => 'View customer orders', 'category' => 'orders', 'priority' => 100],
            ['name' => 'manage_orders', 'description' => 'Manage and update orders', 'category' => 'orders', 'priority' => 90],
            ['name' => 'cancel_orders', 'description' => 'Cancel customer orders', 'category' => 'orders', 'priority' => 80],
            ['name' => 'refund_orders', 'description' => 'Process order refunds', 'category' => 'orders', 'priority' => 70],

            // Kitchen Management
            ['name' => 'view_kitchen', 'description' => 'View kitchen operations', 'category' => 'kitchen', 'priority' => 100],
            ['name' => 'manage_kitchen', 'description' => 'Manage kitchen operations', 'category' => 'kitchen', 'priority' => 90],
            ['name' => 'manage_plats', 'description' => 'Create and manage plats', 'category' => 'kitchen', 'priority' => 80],
            ['name' => 'manage_menus', 'description' => 'Create and manage menus', 'category' => 'kitchen', 'priority' => 70],

            // Inventory Management
            ['name' => 'view_inventory', 'description' => 'View inventory levels', 'category' => 'inventory', 'priority' => 100],
            ['name' => 'manage_inventory', 'description' => 'Manage inventory and stock', 'category' => 'inventory', 'priority' => 90],
            ['name' => 'view_suppliers', 'description' => 'View supplier information', 'category' => 'inventory', 'priority' => 80],
            ['name' => 'manage_suppliers', 'description' => 'Manage supplier relationships', 'category' => 'inventory', 'priority' => 70],

            // Customer Management
            ['name' => 'view_customers', 'description' => 'View customer information', 'category' => 'customers', 'priority' => 100],
            ['name' => 'manage_customers', 'description' => 'Manage customer accounts', 'category' => 'customers', 'priority' => 90],
            ['name' => 'view_customer_analytics', 'description' => 'View customer analytics', 'category' => 'customers', 'priority' => 80],

            // Financial Management
            ['name' => 'view_financial', 'description' => 'View financial reports', 'category' => 'financial', 'priority' => 100],
            ['name' => 'manage_payments', 'description' => 'Manage payments and billing', 'category' => 'financial', 'priority' => 90],
            ['name' => 'view_revenue', 'description' => 'View revenue analytics', 'category' => 'financial', 'priority' => 80],
            ['name' => 'manage_pricing', 'description' => 'Manage pricing and discounts', 'category' => 'financial', 'priority' => 70],

            // System Management
            ['name' => 'manage_system', 'description' => 'Manage system settings', 'category' => 'system', 'priority' => 100],
            ['name' => 'system_health', 'description' => 'View system health and diagnostics', 'category' => 'system', 'priority' => 90],
            ['name' => 'manage_notifications', 'description' => 'Manage system notifications', 'category' => 'system', 'priority' => 80],
            ['name' => 'view_logs', 'description' => 'View system logs', 'category' => 'system', 'priority' => 70],
            ['name' => 'view_activity_logs', 'description' => 'View user activity logs', 'category' => 'system', 'priority' => 65],
            ['name' => 'manage_backups', 'description' => 'Manage system backups', 'category' => 'system', 'priority' => 60],

            // Support & Communication
            ['name' => 'view_support', 'description' => 'View support tickets', 'category' => 'support', 'priority' => 100],
            ['name' => 'manage_support', 'description' => 'Manage customer support', 'category' => 'support', 'priority' => 90],
            ['name' => 'send_notifications', 'description' => 'Send notifications to users', 'category' => 'support', 'priority' => 80],

            // Legacy Permissions (for backward compatibility)
            ['name' => 'dashboard', 'description' => 'Legacy dashboard access', 'category' => 'legacy', 'priority' => 10],
            ['name' => 'users', 'description' => 'Legacy user management', 'category' => 'legacy', 'priority' => 10],
            ['name' => 'orders', 'description' => 'Legacy order management', 'category' => 'legacy', 'priority' => 10],
            ['name' => 'kitchen', 'description' => 'Legacy kitchen access', 'category' => 'legacy', 'priority' => 10],
            ['name' => 'menu', 'description' => 'Legacy menu management', 'category' => 'legacy', 'priority' => 10],
            ['name' => 'inventory', 'description' => 'Legacy inventory access', 'category' => 'legacy', 'priority' => 10],
            ['name' => 'customers', 'description' => 'Legacy customer management', 'category' => 'legacy', 'priority' => 10],
            ['name' => 'reports', 'description' => 'Legacy reports access', 'category' => 'legacy', 'priority' => 10],
            ['name' => 'settings', 'description' => 'Legacy settings access', 'category' => 'legacy', 'priority' => 10],
            ['name' => 'support', 'description' => 'Legacy support access', 'category' => 'legacy', 'priority' => 10],
        ];

        $permissions = [];
        foreach ($permissionsData as $data) {
            $permission = new Permission();
            $permission->setName($data['name']);
            $permission->setDescription($data['description']);
            $permission->setCategory($data['category']);
            $permission->setPriority($data['priority']);
            $permission->setIsActive(true);

            $manager->persist($permission);
            $permissions[$data['name']] = $permission;
        }

        return $permissions;
    }

    private function createRoles(ObjectManager $manager, array $permissions): void
    {
        $rolesData = [
            [
                'name' => 'super_admin_role',
                'description' => 'Super Administrator - Full system access',
                'priority' => 100,
                'permissions' => array_keys($permissions) // All permissions
            ],
            [
                'name' => 'admin_role',
                'description' => 'Administrator - Standard admin access',
                'priority' => 90,
                'permissions' => [
                    'view_dashboard', 'view_analytics', 'export_data',
                    'view_admins', 'manage_admins', 'edit_admin', 'view_user_details',
                    'view_permissions', 'view_roles', 'view_permission_matrix',
                    'view_orders', 'manage_orders', 'cancel_orders',
                    'view_kitchen', 'manage_kitchen', 'manage_plats', 'manage_menus',
                    'view_inventory', 'manage_inventory', 'view_suppliers',
                    'view_customers', 'manage_customers', 'view_customer_analytics',
                    'view_financial', 'view_revenue',
                    'view_logs', 'view_activity_logs',
                    'view_support', 'manage_support', 'send_notifications',
                    // Legacy permissions
                    'dashboard', 'users', 'orders', 'kitchen', 'menu', 'inventory',
                    'customers', 'reports', 'support'
                ]
            ],
            [
                'name' => 'kitchen_manager_role',
                'description' => 'Kitchen Manager - Kitchen operations focus',
                'priority' => 80,
                'permissions' => [
                    'view_dashboard',
                    'view_orders', 'manage_orders',
                    'view_kitchen', 'manage_kitchen', 'manage_plats', 'manage_menus',
                    'view_inventory', 'manage_inventory',
                    'view_customers',
                    // Legacy permissions
                    'dashboard', 'orders', 'kitchen', 'menu', 'inventory'
                ]
            ],
            [
                'name' => 'customer_service_role',
                'description' => 'Customer Service - Customer and support focus',
                'priority' => 70,
                'permissions' => [
                    'view_dashboard',
                    'view_orders', 'manage_orders', 'cancel_orders', 'refund_orders',
                    'view_customers', 'manage_customers', 'view_customer_analytics',
                    'view_support', 'manage_support', 'send_notifications',
                    // Legacy permissions
                    'dashboard', 'orders', 'customers', 'support'
                ]
            ],
            [
                'name' => 'analyst_role',
                'description' => 'Data Analyst - Analytics and reporting focus',
                'priority' => 60,
                'permissions' => [
                    'view_dashboard', 'view_analytics', 'export_data',
                    'view_orders', 'view_customers', 'view_customer_analytics',
                    'view_financial', 'view_revenue',
                    'view_inventory',
                    // Legacy permissions
                    'dashboard', 'reports', 'customers'
                ]
            ],
            [
                'name' => 'viewer_role',
                'description' => 'Read-Only Viewer - View access only',
                'priority' => 50,
                'permissions' => [
                    'view_dashboard',
                    'view_orders', 'view_kitchen', 'view_inventory',
                    'view_customers', 'view_support',
                    // Legacy permissions
                    'dashboard'
                ]
            ]
        ];

        foreach ($rolesData as $roleData) {
            $role = new Role();
            $role->setName($roleData['name']);
            $role->setDescription($roleData['description']);
            $role->setPriority($roleData['priority']);
            $role->setIsActive(true);

            // Add permissions to role
            foreach ($roleData['permissions'] as $permissionName) {
                if (isset($permissions[$permissionName])) {
                    $role->addPermission($permissions[$permissionName]);
                }
            }

            $manager->persist($role);
        }
    }
} 