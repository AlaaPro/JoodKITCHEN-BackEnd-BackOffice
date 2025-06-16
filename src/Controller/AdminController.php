<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\AdminProfile;
use App\Entity\Permission;
use App\Entity\Role;
use App\Service\PermissionService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AdminController extends AbstractController
{
    public function __construct(
        private PermissionService $permissionService
    ) {}

    #[Route('/api/admin/create-user', name: 'api_admin_create_user', methods: ['POST'])]
    #[IsGranted('manage_admins')]
    public function createAdminUser(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        ValidatorInterface $validator
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return new JsonResponse([
                    'error' => 'Données invalides',
                    'message' => 'Les données JSON envoyées ne sont pas valides.',
                    'type' => 'validation_error'
                ], 400);
            }
            
            // Required fields validation
            $requiredFields = ['email', 'password', 'nom', 'prenom'];
            $missingFields = [];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    $missingFields[] = $field;
                }
            }
            
            if (!empty($missingFields)) {
                return new JsonResponse([
                    'error' => 'Champs requis manquants',
                    'message' => 'Les champs suivants sont obligatoires : ' . implode(', ', $missingFields),
                    'missing_fields' => $missingFields,
                    'type' => 'validation_error'
                ], 400);
            }
            
            // Check if email already exists
            $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $data['email']]);
            if ($existingUser) {
                return new JsonResponse([
                    'error' => 'Email déjà utilisé',
                    'message' => 'Un utilisateur avec cet email existe déjà. Veuillez utiliser un autre email.',
                    'type' => 'duplicate_email'
                ], 409);
            }
            
            // Create new user
            $user = new User();
            $user->setNom(trim($data['nom']));
            $user->setPrenom(trim($data['prenom']));
            $user->setEmail(strtolower(trim($data['email'])));
            $user->setTelephone($data['telephone'] ?? null);
            $user->setRoles($data['roles'] ?? ['ROLE_ADMIN']);
            $user->setIsActive($data['is_active'] ?? true);
            
            // Optional fields
            if (!empty($data['ville'])) {
                $user->setVille(trim($data['ville']));
            }
            if (!empty($data['adresse'])) {
                $user->setAdresse(trim($data['adresse']));
            }
            if (!empty($data['genre'])) {
                $user->setGenre($data['genre']);
            }
            if (!empty($data['date_naissance'])) {
                $user->setDateNaissance(new \DateTime($data['date_naissance']));
            }
            
            // Hash password
            $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
            $user->setPassword($hashedPassword);
            
            // Validate user
            $errors = $validator->validate($user);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
                }
                return new JsonResponse([
                    'error' => 'Erreurs de validation',
                    'message' => 'Les données saisies ne sont pas valides.',
                    'details' => $errorMessages,
                    'type' => 'validation_error'
                ], 400);
            }
            
            // Create AdminProfile
            $adminProfile = new AdminProfile();
            $adminProfile->setUser($user);
            
            // Set internal roles if provided (using new normalized approach)
            if (!empty($data['roles_internes']) && is_array($data['roles_internes'])) {
                // Keep JSON for backward compatibility
                $adminProfile->setRolesInternes($data['roles_internes']);
                
                // Also set normalized roles
                foreach ($data['roles_internes'] as $roleName) {
                    $role = $entityManager->getRepository(Role::class)->findOneBy(['name' => $roleName]);
                    if ($role) {
                        $adminProfile->addRole($role);
                    }
                }
            }
            
            // Set advanced permissions if provided (using new normalized approach)
            if (!empty($data['permissions_avancees']) && is_array($data['permissions_avancees'])) {
                // Keep JSON for backward compatibility
                $adminProfile->setPermissionsAvancees($data['permissions_avancees']);
                
                // Also set normalized permissions
                foreach ($data['permissions_avancees'] as $permissionName) {
                    $permission = $entityManager->getRepository(Permission::class)->findOneBy(['name' => $permissionName]);
                    if ($permission) {
                        $adminProfile->addPermission($permission);
                    }
                }
            } else {
                // Auto-assign default permissions based on user roles
                $defaultPermissions = $this->getDefaultPermissionsForRole($user->getRoles());
                $adminProfile->setPermissionsAvancees($defaultPermissions);
                
                // Also set normalized permissions
                foreach ($defaultPermissions as $permissionName) {
                    $permission = $entityManager->getRepository(Permission::class)->findOneBy(['name' => $permissionName]);
                    if ($permission) {
                        $adminProfile->addPermission($permission);
                    }
                }
            }
            
            // Set internal notes if provided
            if (!empty($data['notes_interne'])) {
                $adminProfile->setNotesInterne(trim($data['notes_interne']));
            }
            
            // Validate admin profile
            $profileErrors = $validator->validate($adminProfile);
            if (count($profileErrors) > 0) {
                $errorMessages = [];
                foreach ($profileErrors as $error) {
                    $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
                }
                return new JsonResponse([
                    'error' => 'Erreurs de validation du profil admin',
                    'message' => 'Les données du profil administrateur ne sont pas valides.',
                    'details' => $errorMessages,
                    'type' => 'validation_error'
                ], 400);
            }
            
            // Save both entities in transaction
            $entityManager->beginTransaction();
            try {
                $entityManager->persist($user);
                $entityManager->persist($adminProfile);
                $entityManager->flush();
                $entityManager->commit();
                
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Administrateur créé avec succès',
                    'type' => 'success',
                    'user' => [
                        'id' => $user->getId(),
                        'nom' => $user->getNom(),
                        'prenom' => $user->getPrenom(),
                        'email' => $user->getEmail(),
                        'telephone' => $user->getTelephone(),
                        'roles' => $user->getRoles(),
                        'is_active' => $user->getIsActive(),
                        'created_at' => $user->getCreatedAt()->format('Y-m-d H:i:s')
                    ],
                    'admin_profile' => [
                        'id' => $adminProfile->getId(),
                        'roles_internes' => $adminProfile->getRolesInternes(),
                        'permissions_avancees' => $adminProfile->getPermissionsAvancees(),
                        'notes_interne' => $adminProfile->getNotesInterne()
                    ]
                ], 201);
                
            } catch (UniqueConstraintViolationException $e) {
                $entityManager->rollback();
                return new JsonResponse([
                    'error' => 'Email déjà utilisé',
                    'message' => 'Un utilisateur avec cet email existe déjà. Veuillez utiliser un autre email.',
                    'type' => 'duplicate_email'
                ], 409);
            } catch (\Exception $e) {
                $entityManager->rollback();
                throw $e;
            }
            
        } catch (UniqueConstraintViolationException $e) {
            return new JsonResponse([
                'error' => 'Email déjà utilisé',
                'message' => 'Un utilisateur avec cet email existe déjà. Veuillez utiliser un autre email.',
                'type' => 'duplicate_email'
            ], 409);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de la création',
                'message' => 'Une erreur inattendue s\'est produite. Veuillez réessayer.',
                'type' => 'server_error',
                'debug' => $this->getParameter('kernel.environment') === 'dev' ? $e->getMessage() : null
            ], 500);
        }
    }

    #[Route('/api/admin/roles/internal', name: 'api_admin_roles_internal', methods: ['GET'])]
    #[IsGranted('view_roles')]
    public function getInternalRoles(EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            // Load roles from database
            $roles = $entityManager->getRepository(Role::class)
                ->createQueryBuilder('r')
                ->leftJoin('r.permissions', 'p')
                ->addSelect('p')
                ->orderBy('r.name', 'ASC')
                ->getQuery()
                ->getResult();

            $rolesData = [];
            foreach ($roles as $role) {
                $permissions = [];
                foreach ($role->getPermissions() as $permission) {
                    $permissions[] = $permission->getName();
                }

                $rolesData[] = [
                    'id' => $role->getName(),
                    'name' => $role->getName(),
                    'description' => $role->getDescription(),
                    'permissions' => $permissions
                ];
            }

            return new JsonResponse($rolesData);

        } catch (\Exception $e) {
            // Fallback to static roles if database fails
            error_log('Failed to load roles from database: ' . $e->getMessage());
            
            $fallbackRoles = [
                [
                    'id' => 'manager_general',
                    'name' => 'Manager Général',
                    'description' => 'Responsable général des opérations',
                    'permissions' => ['PERM_dashboard', 'PERM_view_admins', 'PERM_manage_orders']
                ],
                [
                    'id' => 'chef_cuisine',
                    'name' => 'Chef de Cuisine',
                    'description' => 'Responsable de la cuisine et du menu',
                    'permissions' => ['PERM_manage_kitchen', 'PERM_manage_menu']
                ],
                [
                    'id' => 'responsable_it',
                    'name' => 'Responsable IT',
                    'description' => 'Responsable technique et système',
                    'permissions' => ['PERM_system_admin', 'PERM_manage_admins']
                ],
                [
                    'id' => 'manager_service',
                    'name' => 'Manager Service',
                    'description' => 'Responsable du service client',
                    'permissions' => ['PERM_manage_clients', 'PERM_manage_orders']
                ]
            ];

            return new JsonResponse($fallbackRoles);
        }
    }

    #[Route('/api/admin/permissions', name: 'api_admin_permissions', methods: ['GET'])]
    #[IsGranted('view_permissions')]
    public function getAvailablePermissions(EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            // Load permissions from database
            $permissions = $entityManager->getRepository(Permission::class)
                ->createQueryBuilder('p')
                ->orderBy('p.category', 'ASC')
                ->addOrderBy('p.name', 'ASC')
                ->getQuery()
                ->getResult();

            $permissionsData = [];
            foreach ($permissions as $permission) {
                $permissionsData[] = [
                    'id' => $permission->getName(),
                    'name' => $permission->getName(),
                    'category' => $permission->getCategory(),
                    'description' => $permission->getDescription()
                ];
            }

            return new JsonResponse($permissionsData);

        } catch (\Exception $e) {
            // Fallback to static permissions if database fails
            error_log('Failed to load permissions from database: ' . $e->getMessage());
            
            $fallbackPermissions = [
                [
                    'id' => 'PERM_dashboard',
                    'name' => 'Tableau de Bord',
                    'category' => 'General',
                    'description' => 'Accès au tableau de bord principal'
                ],
                [
                    'id' => 'PERM_manage_admins',
                    'name' => 'Gestion Administrateurs',
                    'category' => 'Administration',
                    'description' => 'Créer, modifier, supprimer des administrateurs'
                ],
                [
                    'id' => 'PERM_view_admins',
                    'name' => 'Voir Administrateurs',
                    'category' => 'Administration',
                    'description' => 'Voir la liste des administrateurs'
                ],
                [
                    'id' => 'PERM_manage_orders',
                    'name' => 'Gestion Commandes',
                    'category' => 'Operations',
                    'description' => 'Voir et gérer les commandes'
                ],
                [
                    'id' => 'PERM_manage_kitchen',
                    'name' => 'Gestion Cuisine',
                    'category' => 'Operations',
                    'description' => 'Accès aux outils de cuisine'
                ]
            ];

            return new JsonResponse($fallbackPermissions);
        }
    }

    #[Route('/api/admin/current-user-permissions', name: 'api_admin_current_user_permissions', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getCurrentUserPermissions(): JsonResponse
    {
        try {
            /** @var User $currentUser */
            $currentUser = $this->getUser();
            
            // ✨ NEW: Use PermissionService to get comprehensive permissions
            $permissions = $this->permissionService->getUserPermissions($currentUser);
            
            $adminProfile = $currentUser->getAdminProfile();
            
            return new JsonResponse([
                'success' => true,
                'permissions' => $permissions,
                'roles' => $currentUser->getRoles(),
                'internal_roles' => $adminProfile?->getRolesInternes() ?? [],
                'legacy_permissions' => $adminProfile?->getPermissionsAvancees() ?? [], // For backward compatibility
                'normalized_permissions' => $adminProfile ? $adminProfile->getAllPermissionNames() : [],
                'permission_count' => count($permissions),
                'system_info' => [
                    'new_permission_system' => true,
                    'cached_permissions' => true,
                    'voter_based' => true
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de la récupération des permissions',
                'message' => 'Une erreur s\'est produite lors du chargement des permissions.',
                'type' => 'server_error'
            ], 500);
        }
    }

    #[Route('/api/admin/users', name: 'api_admin_users', methods: ['GET'])]
    #[IsGranted('view_admins')]
    public function getAdminUsers(EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            // Debug: Get all users first to see what we have
            $allUsers = $entityManager->getRepository(User::class)
                ->createQueryBuilder('u')
                ->getQuery()
                ->getResult();
            
            error_log('Total users in database: ' . count($allUsers));
            foreach ($allUsers as $user) {
                error_log('User ID: ' . $user->getId() . ', Email: ' . $user->getEmail() . ', Roles: ' . json_encode($user->getRoles()));
            }
            
            // Get all users with ROLE_ADMIN or ROLE_SUPER_ADMIN
            $users = $entityManager->getRepository(User::class)
                ->createQueryBuilder('u')
                ->leftJoin('u.adminProfile', 'ap')
                ->where('u.roles LIKE :admin_role OR u.roles LIKE :super_admin_role')
                ->setParameter('admin_role', '%ROLE_ADMIN%')
                ->setParameter('super_admin_role', '%ROLE_SUPER_ADMIN%')
                ->orderBy('u.createdAt', 'DESC')
                ->getQuery()
                ->getResult();
            
            error_log('Admin users found: ' . count($users));

            /** @var User $currentUser */
            $currentUser = $this->getUser();
            $adminUsersData = [];
            foreach ($users as $user) {
                $adminProfile = $user->getAdminProfile();
                
                $adminUsersData[] = [
                    'id' => $user->getId(),
                    'nom' => $user->getNom(),
                    'prenom' => $user->getPrenom(),
                    'email' => $user->getEmail(),
                    'telephone' => $user->getTelephone(),
                    'roles' => $user->getRoles(),
                    'is_active' => $user->getIsActive(),
                    'last_connexion' => $user->getLastConnexion()?->format('Y-m-d H:i:s'),
                    'created_at' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
                    'updated_at' => $user->getUpdatedAt()->format('Y-m-d H:i:s'),
                    'can_edit' => $this->isGranted('EDIT_ADMIN_USER', $user),
                    'can_delete' => $this->isGranted('DELETE_ADMIN_USER', $user),
                    'contextual_permissions' => $this->permissionService->getUserContextualPermissions($currentUser, $user),
                    'admin_profile' => $adminProfile ? [
                        'id' => $adminProfile->getId(),
                        'roles_internes' => $adminProfile->getRolesInternes(),
                        'permissions_avancees' => $adminProfile->getPermissionsAvancees(),
                        'notes_interne' => $adminProfile->getNotesInterne(),
                        'created_at' => $adminProfile->getCreatedAt()->format('Y-m-d H:i:s'),
                        'updated_at' => $adminProfile->getUpdatedAt()->format('Y-m-d H:i:s'),
                        // ✨ NEW: Include normalized data
                        'normalized_roles' => array_map(fn($role) => $role->getName(), $adminProfile->getRoles()->toArray()),
                        'normalized_permissions' => array_map(fn($perm) => $perm->getName(), $adminProfile->getPermissions()->toArray())
                    ] : null
                ];
            }

            return new JsonResponse([
                'success' => true,
                'data' => $adminUsersData,
                'count' => count($adminUsersData)
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de la récupération des administrateurs',
                'message' => 'Une erreur s\'est produite lors du chargement des données.',
                'type' => 'server_error',
                'debug' => $this->getParameter('kernel.environment') === 'dev' ? $e->getMessage() : null
            ], 500);
        }
    }

    #[Route('/api/admin/update-user/{id}', name: 'api_admin_update_user', methods: ['PUT'])]
    public function updateAdminUser(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        ValidatorInterface $validator
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return new JsonResponse([
                    'error' => 'Données invalides',
                    'message' => 'Les données JSON envoyées ne sont pas valides.',
                    'type' => 'validation_error'
                ], 400);
            }
            
            // Find user with admin profile
            $user = $entityManager->getRepository(User::class)->find($id);
            if (!$user) {
                return new JsonResponse([
                    'error' => 'Utilisateur non trouvé',
                    'message' => 'L\'administrateur demandé n\'existe pas.',
                    'type' => 'not_found'
                ], 404);
            }

            // ✨ NEW: Use Symfony Voter for permission checking
            $this->denyAccessUnlessGranted('EDIT_ADMIN_USER', $user);
            
            // Check if email is already used by another user
            if (isset($data['email']) && $data['email'] !== $user->getEmail()) {
                $existingUser = $entityManager->getRepository(User::class)
                    ->findOneBy(['email' => $data['email']]);
                if ($existingUser && $existingUser->getId() !== $id) {
                    return new JsonResponse([
                        'error' => 'Email déjà utilisé',
                        'message' => 'Cette adresse email est déjà utilisée par un autre utilisateur.',
                        'type' => 'duplicate_email'
                    ], 409);
                }
            }
            
            // Update user data
            if (isset($data['nom'])) $user->setNom(trim($data['nom']));
            if (isset($data['prenom'])) $user->setPrenom(trim($data['prenom']));
            if (isset($data['email'])) $user->setEmail(strtolower(trim($data['email'])));
            if (isset($data['telephone'])) $user->setTelephone($data['telephone']);
            if (isset($data['ville'])) $user->setVille($data['ville']);
            if (isset($data['adresse'])) $user->setAdresse($data['adresse']);
            if (isset($data['roles'])) $user->setRoles($data['roles']);
            if (isset($data['is_active'])) $user->setIsActive($data['is_active']);
            
            // Update password if provided
            if (!empty($data['password'])) {
                $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
                $user->setPassword($hashedPassword);
            }
            
            // Validate user
            $errors = $validator->validate($user);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
                }
                return new JsonResponse([
                    'error' => 'Erreurs de validation',
                    'message' => 'Les données saisies ne sont pas valides.',
                    'details' => $errorMessages,
                    'type' => 'validation_error'
                ], 400);
            }
            
            // Update or create admin profile
            $adminProfile = $user->getAdminProfile();
            if (!$adminProfile) {
                $adminProfile = new AdminProfile();
                $adminProfile->setUser($user);
                $entityManager->persist($adminProfile);
            }
            
            // Update admin profile data (using new normalized approach)
            if (isset($data['roles_internes'])) {
                // Keep JSON for backward compatibility
                $adminProfile->setRolesInternes($data['roles_internes']);
                
                // Clear existing normalized roles and set new ones
                foreach ($adminProfile->getRoles() as $role) {
                    $adminProfile->removeRole($role);
                }
                
                // Add new normalized roles
                foreach ($data['roles_internes'] as $roleName) {
                    $role = $entityManager->getRepository(Role::class)->findOneBy(['name' => $roleName]);
                    if ($role) {
                        $adminProfile->addRole($role);
                    }
                }
            }
            
            if (isset($data['permissions_avancees'])) {
                // Keep JSON for backward compatibility
                $adminProfile->setPermissionsAvancees($data['permissions_avancees']);
                
                // Clear existing normalized permissions and set new ones
                foreach ($adminProfile->getPermissions() as $permission) {
                    $adminProfile->removePermission($permission);
                }
                
                // Add new normalized permissions
                foreach ($data['permissions_avancees'] as $permissionName) {
                    $permission = $entityManager->getRepository(Permission::class)->findOneBy(['name' => $permissionName]);
                    if ($permission) {
                        $adminProfile->addPermission($permission);
                    }
                }
            }
            if (isset($data['notes_interne'])) {
                $adminProfile->setNotesInterne($data['notes_interne']);
            }
            
            // Validate admin profile
            $profileErrors = $validator->validate($adminProfile);
            if (count($profileErrors) > 0) {
                $errorMessages = [];
                foreach ($profileErrors as $error) {
                    $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
                }
                return new JsonResponse([
                    'error' => 'Erreurs de validation du profil admin',
                    'message' => 'Les données du profil administrateur ne sont pas valides.',
                    'details' => $errorMessages,
                    'type' => 'validation_error'
                ], 400);
            }
            
            // Save both entities in transaction
            $entityManager->beginTransaction();
            try {
                $entityManager->persist($user);
                $entityManager->persist($adminProfile);
                $entityManager->flush();
                $entityManager->commit();
                
                // ✨ NEW: Invalidate permission cache after user update
                $this->permissionService->invalidateUserCache($user);
                
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Administrateur modifié avec succès',
                    'type' => 'success',
                    'user' => [
                        'id' => $user->getId(),
                        'nom' => $user->getNom(),
                        'prenom' => $user->getPrenom(),
                        'email' => $user->getEmail(),
                        'telephone' => $user->getTelephone(),
                        'roles' => $user->getRoles(),
                        'is_active' => $user->getIsActive(),
                        'updated_at' => $user->getUpdatedAt()->format('Y-m-d H:i:s')
                    ],
                    'admin_profile' => [
                        'id' => $adminProfile->getId(),
                        'roles_internes' => $adminProfile->getRolesInternes(),
                        'permissions_avancees' => $adminProfile->getPermissionsAvancees(),
                        'notes_interne' => $adminProfile->getNotesInterne()
                    ]
                ]);
                
            } catch (UniqueConstraintViolationException $e) {
                $entityManager->rollback();
                return new JsonResponse([
                    'error' => 'Email déjà utilisé',
                    'message' => 'Cette adresse email est déjà utilisée par un autre utilisateur.',
                    'type' => 'duplicate_email'
                ], 409);
            } catch (\Exception $e) {
                $entityManager->rollback();
                throw $e;
            }
            
        } catch (UniqueConstraintViolationException $e) {
            return new JsonResponse([
                'error' => 'Email déjà utilisé',
                'message' => 'Cette adresse email est déjà utilisée par un autre utilisateur.',
                'type' => 'duplicate_email'
            ], 409);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de la modification',
                'message' => 'Une erreur inattendue s\'est produite. Veuillez réessayer.',
                'type' => 'server_error',
                'debug' => $this->getParameter('kernel.environment') === 'dev' ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * ✨ NEW: Modern permission checking using PermissionService
     * Get default permissions based on user roles (legacy compatibility)
     */
    private function getDefaultPermissionsForRole(array $roles): array
    {
        $permissions = [];

        if (in_array('ROLE_SUPER_ADMIN', $roles)) {
            // Super admin gets all permissions
            $permissions = [
                'dashboard', 'users', 'orders', 'kitchen', 'menu', 'inventory',
                'customers', 'reports', 'settings', 'system', 'support',
                'edit_admin', 'edit_super_admin', 'manage_permissions'
            ];
        } elseif (in_array('ROLE_ADMIN', $roles)) {
            // Regular admin gets basic permissions (no edit_super_admin)
            $permissions = [
                'dashboard', 'users', 'orders', 'kitchen', 'menu', 'inventory',
                'customers', 'reports', 'support', 'edit_admin'
            ];
        }

        return $permissions;
    }

    /**
     * ✨ NEW: Health check endpoint for permission system
     */
    #[Route('/api/admin/permissions/health', name: 'api_admin_permissions_health', methods: ['GET'])]
    #[IsGranted('view_logs')]
    public function permissionSystemHealth(): JsonResponse
    {
        $healthCheck = $this->permissionService->healthCheck();
        
        return new JsonResponse([
            'success' => true,
            'permission_system' => $healthCheck,
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * ✨ NEW: Get user permissions with caching
     */
    #[Route('/api/admin/user/{id}/permissions', name: 'api_admin_user_permissions', methods: ['GET'])]
    #[IsGranted('view_permissions')]
    public function getUserPermissions(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $entityManager->getRepository(User::class)->find($id);
        if (!$user) {
            return new JsonResponse([
                'error' => 'Utilisateur non trouvé',
                'type' => 'not_found'
            ], 404);
        }

        $permissions = $this->permissionService->getUserPermissions($user);
        $contextualPermissions = $this->permissionService->getUserContextualPermissions($this->getUser(), $user);

        return new JsonResponse([
            'success' => true,
            'user_id' => $user->getId(),
            'permissions' => $permissions,
            'contextual_permissions' => $contextualPermissions,
            'permission_count' => count($permissions)
        ]);
    }
} 