<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\AdminProfile;
use App\Entity\Permission;
use App\Entity\Role;
use App\Service\PermissionService;
use App\Service\LogSystemService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Repository\CommandeRepository;
use App\Service\CacheService;

class AdminController extends AbstractController
{
    public function __construct(
        private PermissionService $permissionService,
        private LogSystemService $logSystemService,
        private \App\Service\UserActivityService $userActivityService
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
                        // ✨ Profile picture data
                        'photo_profil' => $user->getPhotoProfil(),
                        'photo_profil_url' => $user->getPhotoProfilUrl(),
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
                    // ✨ Profile picture data
                    'photo_profil' => $user->getPhotoProfil(),
                    'photo_profil_url' => $user->getPhotoProfilUrl(),
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
                        // ✨ Profile picture data
                        'photo_profil' => $user->getPhotoProfil(),
                        'photo_profil_url' => $user->getPhotoProfilUrl(),
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

    #[Route('/api/admin/check-permissions/{targetUserId}', name: 'api_admin_check_permissions', methods: ['GET'])]
    #[IsGranted('view_permissions')]
    public function checkUserPermissions(int $targetUserId, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            /** @var User $currentUser */
            $currentUser = $this->getUser();
            
            $targetUser = $entityManager->getRepository(User::class)->find($targetUserId);
            if (!$targetUser) {
                return new JsonResponse([
                    'error' => 'Utilisateur non trouvé',
                    'message' => 'L\'utilisateur cible n\'existe pas.',
                    'type' => 'not_found'
                ], 404);
            }

            // Get detailed permissions for the target user
            $permissions = [
                'can_view_details' => $this->isGranted('VIEW_ADMIN_DETAILS', $targetUser),
                'can_edit' => $this->isGranted('EDIT_ADMIN_USER', $targetUser),
                'can_delete' => $this->isGranted('DELETE_ADMIN_USER', $targetUser),
                'can_manage_permissions' => $this->isGranted('MANAGE_ADMIN_PERMISSIONS', $targetUser),
            ];

            // Get specific permission breakdown
            $specificPermissions = [
                'manage_admins' => $this->permissionService->hasPermission($currentUser, 'manage_admins'),
                'edit_admin' => $this->permissionService->hasPermission($currentUser, 'edit_admin'),
                'edit_super_admin' => $this->permissionService->hasPermission($currentUser, 'edit_super_admin'),
                'delete_admin' => $this->permissionService->hasPermission($currentUser, 'delete_admin'),
                'view_permissions' => $this->permissionService->hasPermission($currentUser, 'view_permissions'),
            ];

            // Get user role information
            $currentUserRoles = $currentUser->getRoles();
            $targetUserRoles = $targetUser->getRoles();

            return new JsonResponse([
                'success' => true,
                'current_user' => [
                    'id' => $currentUser->getId(),
                    'email' => $currentUser->getEmail(),
                    'roles' => $currentUserRoles,
                    'is_super_admin' => in_array('ROLE_SUPER_ADMIN', $currentUserRoles)
                ],
                'target_user' => [
                    'id' => $targetUser->getId(),
                    'email' => $targetUser->getEmail(),
                    'roles' => $targetUserRoles,
                    'is_super_admin' => in_array('ROLE_SUPER_ADMIN', $targetUserRoles)
                ],
                'voter_permissions' => $permissions,
                'specific_permissions' => $specificPermissions,
                'permission_explanation' => [
                    'edit_logic' => $this->explainEditPermission($currentUser, $targetUser),
                    'delete_logic' => $this->explainDeletePermission($currentUser, $targetUser),
                ],
                'system_info' => [
                    'permission_system_version' => '2.0',
                    'uses_advanced_permissions' => true,
                    'fallback_to_roles' => false
                ]
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de la vérification des permissions',
                'message' => 'Une erreur s\'est produite lors de la vérification.',
                'type' => 'server_error',
                'debug' => $this->getParameter('kernel.environment') === 'dev' ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Explain why user can or cannot edit target user
     */
    private function explainEditPermission(User $currentUser, User $targetUser): array
    {
        $explanation = [];
        $currentRoles = $currentUser->getRoles();
        $targetRoles = $targetUser->getRoles();

        if ($currentUser->getId() === $targetUser->getId()) {
            $explanation[] = "Cannot edit yourself through admin management";
            return ['allowed' => false, 'reasons' => $explanation];
        }

        if (in_array('ROLE_SUPER_ADMIN', $currentRoles)) {
            $explanation[] = "User is SUPER_ADMIN";
            
            $hasManageAdmins = $this->permissionService->hasPermission($currentUser, 'manage_admins');
            $hasEditAdmin = $this->permissionService->hasPermission($currentUser, 'edit_admin');
            $hasEditSuperAdmin = $this->permissionService->hasPermission($currentUser, 'edit_super_admin');
            
            if ($hasManageAdmins || $hasEditAdmin || $hasEditSuperAdmin) {
                $explanation[] = "Has required permissions: " . 
                    ($hasManageAdmins ? 'manage_admins ' : '') .
                    ($hasEditAdmin ? 'edit_admin ' : '') .
                    ($hasEditSuperAdmin ? 'edit_super_admin' : '');
                return ['allowed' => true, 'reasons' => $explanation];
            } else {
                $explanation[] = "Missing required permissions (manage_admins, edit_admin, or edit_super_admin)";
                return ['allowed' => false, 'reasons' => $explanation];
            }
        }

        if (in_array('ROLE_SUPER_ADMIN', $targetRoles)) {
            $hasEditSuperAdmin = $this->permissionService->hasPermission($currentUser, 'edit_super_admin');
            if ($hasEditSuperAdmin) {
                $explanation[] = "Target is SUPER_ADMIN but user has edit_super_admin permission";
                return ['allowed' => true, 'reasons' => $explanation];
            } else {
                $explanation[] = "Target is SUPER_ADMIN and user lacks edit_super_admin permission";
                return ['allowed' => false, 'reasons' => $explanation];
            }
        }

        if (in_array('ROLE_ADMIN', $targetRoles)) {
            $hasEditAdmin = $this->permissionService->hasPermission($currentUser, 'edit_admin');
            $hasManageAdmins = $this->permissionService->hasPermission($currentUser, 'manage_admins');
            
            if ($hasEditAdmin || $hasManageAdmins) {
                $explanation[] = "Target is ADMIN and user has " . 
                    ($hasEditAdmin ? 'edit_admin' : '') .
                    ($hasManageAdmins ? ($hasEditAdmin ? ' or manage_admins' : 'manage_admins') : '');
                return ['allowed' => true, 'reasons' => $explanation];
            } else {
                $explanation[] = "Target is ADMIN but user lacks edit_admin or manage_admins permission";
                return ['allowed' => false, 'reasons' => $explanation];
            }
        }

        $hasManageAdmins = $this->permissionService->hasPermission($currentUser, 'manage_admins');
        if ($hasManageAdmins) {
            $explanation[] = "User has manage_admins permission for general users";
            return ['allowed' => true, 'reasons' => $explanation];
        } else {
            $explanation[] = "User lacks manage_admins permission";
            return ['allowed' => false, 'reasons' => $explanation];
        }
    }

    /**
     * Explain why user can or cannot delete target user
     */
    private function explainDeletePermission(User $currentUser, User $targetUser): array
    {
        $explanation = [];
        $currentRoles = $currentUser->getRoles();
        $targetRoles = $targetUser->getRoles();

        if ($currentUser->getId() === $targetUser->getId()) {
            $explanation[] = "Cannot delete yourself";
            return ['allowed' => false, 'reasons' => $explanation];
        }

        $hasDeleteAdmin = $this->permissionService->hasPermission($currentUser, 'delete_admin');
        if (!$hasDeleteAdmin) {
            $explanation[] = "User lacks delete_admin permission";
            return ['allowed' => false, 'reasons' => $explanation];
        }

        if (in_array('ROLE_SUPER_ADMIN', $currentRoles)) {
            $explanation[] = "User is SUPER_ADMIN with delete_admin permission";
            
            if (in_array('ROLE_SUPER_ADMIN', $targetRoles)) {
                $hasEditSuperAdmin = $this->permissionService->hasPermission($currentUser, 'edit_super_admin');
                if ($hasEditSuperAdmin) {
                    $explanation[] = "Target is SUPER_ADMIN and user has edit_super_admin permission";
                    return ['allowed' => true, 'reasons' => $explanation];
                } else {
                    $explanation[] = "Target is SUPER_ADMIN but user lacks edit_super_admin permission";
                    return ['allowed' => false, 'reasons' => $explanation];
                }
            } else {
                $explanation[] = "Target is regular admin - deletion allowed";
                return ['allowed' => true, 'reasons' => $explanation];
            }
        }

        if (in_array('ROLE_SUPER_ADMIN', $targetRoles)) {
            $explanation[] = "Regular admin cannot delete super admin";
            return ['allowed' => false, 'reasons' => $explanation];
        }

        if (in_array('ROLE_ADMIN', $targetRoles)) {
            $hasEditAdmin = $this->permissionService->hasPermission($currentUser, 'edit_admin');
            if ($hasEditAdmin) {
                $explanation[] = "Target is ADMIN and user has both delete_admin and edit_admin permissions";
                return ['allowed' => true, 'reasons' => $explanation];
            } else {
                $explanation[] = "Target is ADMIN but user lacks edit_admin permission (needed along with delete_admin)";
                return ['allowed' => false, 'reasons' => $explanation];
            }
        }

        $hasManageAdmins = $this->permissionService->hasPermission($currentUser, 'manage_admins');
        if ($hasManageAdmins) {
            $explanation[] = "User has both delete_admin and manage_admins permissions";
            return ['allowed' => true, 'reasons' => $explanation];
        } else {
            $explanation[] = "User has delete_admin but lacks manage_admins permission";
            return ['allowed' => false, 'reasons' => $explanation];
        }
    }

    // ============================================
    // LOG SYSTEM API ENDPOINTS
    // ============================================

    #[Route('/api/admin/logs/stats', name: 'api_admin_logs_stats', methods: ['GET'])]
    #[IsGranted('view_logs')]
    public function getLogStatistics(): JsonResponse
    {
        try {
            $stats = $this->logSystemService->getLogStatistics();
            return new JsonResponse([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la récupération des statistiques',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/admin/logs', name: 'api_admin_logs', methods: ['GET'])]
    #[IsGranted('view_logs')]
    public function getLogs(Request $request): JsonResponse
    {
        try {
            $filters = [];
            
            // Extract filters from query parameters
            if ($request->query->has('level')) {
                $filters['level'] = $request->query->get('level');
            }
            if ($request->query->has('component')) {
                $filters['component'] = $request->query->get('component');
            }
            if ($request->query->has('dateStart')) {
                $filters['dateStart'] = $request->query->get('dateStart');
            }
            if ($request->query->has('dateEnd')) {
                $filters['dateEnd'] = $request->query->get('dateEnd');
            }
            if ($request->query->has('limit')) {
                $filters['limit'] = (int)$request->query->get('limit');
            }
            
            $logs = $this->logSystemService->getFormattedAuditLogs($filters);
            
            return new JsonResponse([
                'success' => true,
                'data' => $logs,
                'count' => count($logs)
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la récupération des logs',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/admin/logs/recent', name: 'api_admin_logs_recent', methods: ['GET'])]
    #[IsGranted('view_logs')]
    public function getRecentLogs(Request $request): JsonResponse
    {
        try {
            $limit = (int)($request->query->get('limit', 20));
            $since = $request->query->get('since'); // timestamp for updates since last check
            
            $filters = ['limit' => $limit];
            if ($since) {
                $filters['dateStart'] = date('Y-m-d H:i:s', (int)$since);
            }
            
            $logs = $this->logSystemService->getFormattedAuditLogs($filters);
            
            return new JsonResponse([
                'success' => true,
                'data' => $logs,
                'has_updates' => !empty($logs),
                'last_check' => time()
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la récupération des logs récents',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/admin/logs/errors', name: 'api_admin_logs_errors', methods: ['GET'])]
    #[IsGranted('view_logs')]
    public function getRecentErrors(): JsonResponse
    {
        try {
            $errors = $this->logSystemService->getRecentErrors();
            return new JsonResponse([
                'success' => true,
                'data' => $errors
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la récupération des erreurs',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/admin/logs/errors/detailed', name: 'api_admin_logs_errors_detailed', methods: ['GET'])]
    #[IsGranted('view_logs')]
    public function getDetailedErrors(Request $request): JsonResponse
    {
        try {
            $limit = min((int)$request->query->get('limit', 20), 100);
            $errors = $this->logSystemService->getDetailedErrors($limit);
            
            return new JsonResponse([
                'success' => true,
                'data' => $errors,
                'count' => count($errors)
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la récupération des erreurs détaillées',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/admin/logs/distribution', name: 'api_admin_logs_distribution', methods: ['GET'])]
    #[IsGranted('view_logs')]
    public function getLogDistribution(): JsonResponse
    {
        try {
            $distribution = $this->logSystemService->getLogDistribution();
            return new JsonResponse([
                'success' => true,
                'data' => $distribution
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la récupération de la distribution',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/admin/system/health', name: 'api_admin_system_health', methods: ['GET'])]
    #[IsGranted('view_logs')]
    public function getSystemHealth(): JsonResponse
    {
        try {
            $health = $this->logSystemService->getSystemHealth();
            return new JsonResponse([
                'success' => true,
                'data' => $health
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la récupération de la santé système',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/admin/logs/export', name: 'api_admin_logs_export', methods: ['POST'])]
    #[IsGranted('export_logs')]
    public function exportLogs(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            $filters = $data['filters'] ?? [];
            $format = $data['format'] ?? 'csv';
            
            $exportData = $this->logSystemService->exportLogs($filters, $format);
            
            return new JsonResponse([
                'success' => true,
                'data' => $exportData,
                'format' => $format,
                'generated_at' => (new \DateTime())->format('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de l\'export des logs',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // ============================================
    // USER ACTIVITIES API ENDPOINTS
    // ============================================

    #[Route('/api/admin/activities/stats', name: 'api_admin_activities_stats', methods: ['GET'])]
    #[IsGranted('view_logs')]
    public function getActivityStatistics(): JsonResponse
    {
        try {
            $stats = $this->userActivityService->getActivityStats();
            return new JsonResponse([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la récupération des statistiques d\'activité',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/admin/activities', name: 'api_admin_activities', methods: ['GET'])]
    #[IsGranted('view_logs')]
    public function getActivities(Request $request): JsonResponse
    {
        try {
            $criteria = [];
            
            // Extract filters from query parameters
            if ($request->query->has('profileType')) {
                $criteria['profileType'] = $request->query->get('profileType');
            }
            if ($request->query->has('action')) {
                $criteria['action'] = $request->query->get('action');
            }
            if ($request->query->has('entityType')) {
                $criteria['entityType'] = $request->query->get('entityType');
            }
            if ($request->query->has('userId')) {
                $criteria['userId'] = $request->query->get('userId');
            }
            if ($request->query->has('dateStart')) {
                $criteria['dateStart'] = $request->query->get('dateStart');
            }
            if ($request->query->has('dateEnd')) {
                $criteria['dateEnd'] = $request->query->get('dateEnd');
            }
            if ($request->query->has('limit')) {
                $limit = (int)$request->query->get('limit');
            } else {
                $limit = 50;
            }
            
            $activities = $this->userActivityService->getFormattedActivities($criteria, $limit);
            
            return new JsonResponse([
                'success' => true,
                'data' => $activities,
                'count' => count($activities),
                'debug' => [
                    'criteria' => $criteria,
                    'query_params' => $request->query->all()
                ]
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la récupération des activités',
                'message' => $e->getMessage(),
                'debug' => [
                    'criteria' => $criteria ?? [],
                    'query_params' => $request->query->all()
                ]
            ], 500);
        }
    }

    #[Route('/api/admin/activities/recent', name: 'api_admin_activities_recent', methods: ['GET'])]
    #[IsGranted('view_logs')]
    public function getRecentActivities(Request $request): JsonResponse
    {
        try {
            $limit = (int)($request->query->get('limit', 20));
            $since = $request->query->get('since'); // timestamp for updates since last check
            
            $criteria = [];
            if ($since) {
                $criteria['dateStart'] = date('Y-m-d H:i:s', (int)$since);
            }
            
            $activities = $this->userActivityService->getFormattedActivities($criteria, $limit);
            
            return new JsonResponse([
                'success' => true,
                'data' => $activities,
                'has_updates' => !empty($activities),
                'last_check' => time()
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la récupération des activités récentes',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/admin/activities/distribution', name: 'api_admin_activities_distribution', methods: ['GET'])]
    #[IsGranted('view_logs')]
    public function getActivityDistribution(): JsonResponse
    {
        try {
            $distribution = $this->userActivityService->getActivityDistribution();
            return new JsonResponse([
                'success' => true,
                'data' => $distribution
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la récupération de la distribution des activités',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/admin/activities/profiles', name: 'api_admin_activities_profiles', methods: ['GET'])]
    #[IsGranted('view_logs')]
    public function getProfileDistribution(): JsonResponse
    {
        try {
            $distribution = $this->userActivityService->getProfileDistribution();
            return new JsonResponse([
                'success' => true,
                'data' => $distribution
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la récupération de la distribution des profils',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/admin/activities/export', name: 'api_admin_activities_export', methods: ['POST'])]
    #[IsGranted('export_logs')]
    public function exportActivities(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            $filters = $data['filters'] ?? [];
            $format = $data['format'] ?? 'csv';
            
            $exportData = $this->userActivityService->exportActivities($filters, $format);
            
            return new JsonResponse([
                'success' => true,
                'data' => $exportData,
                'format' => $format,
                'generated_at' => (new \DateTime())->format('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de l\'export des activités',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/admin/orders', name: 'api_admin_orders', methods: ['GET'])]
    public function getOrders(Request $request, CommandeRepository $commandeRepository): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(10, (int) $request->query->get('limit', 25)));
        $status = $request->query->get('status');
        $type = $request->query->get('type');
        $search = $request->query->get('search');
        $date = $request->query->get('date');

        $criteria = [];
        if ($status) {
            $criteria['statut'] = $status;
        }
        if ($type) {
            $criteria['typeLivraison'] = $type;
        }

        $qb = $commandeRepository->createQueryBuilder('c')
            ->leftJoin('c.user', 'u')
            ->addSelect('u');

        if ($search) {
            $qb->andWhere('c.id LIKE :search OR u.nom LIKE :search OR u.email LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($status) {
            $qb->andWhere('c.statut = :status')
               ->setParameter('status', $status);
        }

        if ($type) {
            $qb->andWhere('c.typeLivraison = :type')
               ->setParameter('type', $type);
        }

        if ($date) {
            $qb->andWhere('DATE(c.dateCommande) = :date')
               ->setParameter('date', $date);
        }

        $qb->orderBy('c.dateCommande', 'DESC');

        $total = (clone $qb)->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();
        
        $qb->setFirstResult(($page - 1) * $limit)
           ->setMaxResults($limit);

        $orders = $qb->getQuery()->getResult();

        $ordersData = [];
        foreach ($orders as $order) {
            $ordersData[] = [
                'id' => $order->getId(),
                'numero' => 'CMD-' . str_pad($order->getId(), 3, '0', STR_PAD_LEFT),
                'client' => [
                    'nom' => $order->getUser() ? $order->getUser()->getNom() . ' ' . $order->getUser()->getPrenom() : 'Client Anonyme',
                    'email' => $order->getUser() ? $order->getUser()->getEmail() : null,
                ],
                'dateCommande' => $order->getDateCommande()->format('d/m/Y H:i'),
                'typeLivraison' => $order->getTypeLivraison(),
                'total' => $order->getTotal(),
                'statut' => $order->getStatut(),
                'adresseLivraison' => $order->getAdresseLivraison(),
                'commentaire' => $order->getCommentaire(),
                'articlesCount' => $order->getCommandeArticles()->count()
            ];
        }

        return $this->json([
            'success' => true,
            'data' => $ordersData,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int) $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }

    #[Route('/api/admin/orders/stats', name: 'api_admin_orders_stats', methods: ['GET'])]
    public function getOrdersStats(
        CommandeRepository $commandeRepository,
        CacheService $cacheService
    ): JsonResponse
    {
        $cacheKey = 'admin_orders_stats';
        
        // Try to get from cache first
        if ($cacheService->has($cacheKey)) {
            return $this->json([
                'success' => true,
                'data' => $cacheService->get($cacheKey),
                'from_cache' => true
            ]);
        }
        
        // Get fresh stats from database
        $stats = $commandeRepository->getOrderStats();
        
        // Keep only the stats needed for the dashboard
        $dashboardStats = [
            'pending' => $stats['pending'],
            'preparing' => $stats['preparing'],
            'completed' => $stats['completed'],
            'todayRevenue' => $stats['todayRevenue']
        ];
        
        // Cache for 1 minute (stats should be relatively fresh)
        $cacheService->set($cacheKey, $dashboardStats, 60);
        
        return $this->json([
            'success' => true,
            'data' => $dashboardStats,
            'from_cache' => false
        ]);
    }

    #[Route('/api/admin/orders/{id}', name: 'api_admin_order_details', methods: ['GET'])]
    public function getOrderDetails(int $id, CommandeRepository $commandeRepository): JsonResponse
    {
        $order = $commandeRepository->find($id);
        
        if (!$order) {
            return $this->json(['error' => 'Commande non trouvée'], 404);
        }

        $articles = [];
        foreach ($order->getCommandeArticles() as $article) {
            $articles[] = [
                'id' => $article->getId(),
                'nom' => $article->getPlat() ? $article->getPlat()->getNom() : 'Article supprimé',
                'quantite' => $article->getQuantite(),
                'prixUnitaire' => $article->getPrixUnitaire(),
                'total' => $article->getQuantite() * $article->getPrixUnitaire(),
                'commentaire' => $article->getCommentaire()
            ];
        }

        $orderData = [
            'id' => $order->getId(),
            'numero' => 'CMD-' . str_pad($order->getId(), 3, '0', STR_PAD_LEFT),
            'client' => [
                'nom' => $order->getUser() ? $order->getUser()->getNom() . ' ' . $order->getUser()->getPrenom() : 'Client Anonyme',
                'email' => $order->getUser() ? $order->getUser()->getEmail() : null,
                'telephone' => $order->getUser() ? $order->getUser()->getTelephone() : null,
            ],
            'dateCommande' => $order->getDateCommande()->format('d/m/Y H:i:s'),
            'typeLivraison' => $order->getTypeLivraison(),
            'adresseLivraison' => $order->getAdresseLivraison(),
            'statut' => $order->getStatut(),
            'total' => $order->getTotal(),
            'commentaire' => $order->getCommentaire(),
            'articles' => $articles
        ];

        return $this->json([
            'success' => true,
            'data' => $orderData
        ]);
    }

    #[Route('/api/admin/orders/{id}/status', name: 'api_admin_order_update_status', methods: ['PUT'])]
    public function updateOrderStatus(int $id, Request $request, CommandeRepository $commandeRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $order = $commandeRepository->find($id);
        
        if (!$order) {
            return $this->json(['error' => 'Commande non trouvée'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $newStatus = $data['status'] ?? null;

        $validStatuses = ['en_attente', 'en_preparation', 'pret', 'en_livraison', 'livre', 'annule'];
        
        if (!in_array($newStatus, $validStatuses)) {
            return $this->json(['error' => 'Statut invalide'], 400);
        }

        $order->setStatut($newStatus);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Statut mis à jour avec succès',
            'data' => [
                'id' => $order->getId(),
                'status' => $order->getStatut()
            ]
        ]);
    }
} 