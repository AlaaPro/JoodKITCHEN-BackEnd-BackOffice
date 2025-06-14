<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\AdminProfile;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AdminController extends AbstractController
{
    #[Route('/api/admin/create-user', name: 'api_admin_create_user', methods: ['POST'])]
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
            
            // Set internal roles if provided
            if (!empty($data['roles_internes']) && is_array($data['roles_internes'])) {
                $adminProfile->setRolesInternes($data['roles_internes']);
            }
            
            // Set advanced permissions if provided, otherwise set defaults based on role
            if (!empty($data['permissions_avancees']) && is_array($data['permissions_avancees'])) {
                $adminProfile->setPermissionsAvancees($data['permissions_avancees']);
            } else {
                // Auto-assign default permissions based on user roles
                $defaultPermissions = $this->getDefaultPermissionsForRole($user->getRoles());
                $adminProfile->setPermissionsAvancees($defaultPermissions);
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
    public function getInternalRoles(): JsonResponse
    {
        // Define internal roles for the admin system
        $internalRoles = [
            [
                'id' => 'manager_general',
                'name' => 'Manager Général',
                'description' => 'Responsable général des opérations',
                'permissions' => ['dashboard', 'users', 'orders', 'reports']
            ],
            [
                'id' => 'chef_cuisine',
                'name' => 'Chef de Cuisine',
                'description' => 'Responsable de la cuisine et du menu',
                'permissions' => ['kitchen', 'menu', 'inventory']
            ],
            [
                'id' => 'responsable_it',
                'name' => 'Responsable IT',
                'description' => 'Responsable technique et système',
                'permissions' => ['system', 'users', 'settings']
            ],
            [
                'id' => 'manager_service',
                'name' => 'Manager Service',
                'description' => 'Responsable du service client',
                'permissions' => ['customers', 'orders', 'support']
            ]
        ];

        return new JsonResponse($internalRoles);
    }

    #[Route('/api/admin/permissions', name: 'api_admin_permissions', methods: ['GET'])]
    public function getAvailablePermissions(): JsonResponse
    {
        // Define available permissions for the admin system
        $permissions = [
            [
                'id' => 'dashboard',
                'name' => 'Tableau de Bord',
                'category' => 'General',
                'description' => 'Accès au tableau de bord principal'
            ],
            [
                'id' => 'users',
                'name' => 'Gestion Utilisateurs',
                'category' => 'Administration',
                'description' => 'Créer, modifier, supprimer des utilisateurs'
            ],
            [
                'id' => 'orders',
                'name' => 'Gestion Commandes',
                'category' => 'Operations',
                'description' => 'Voir et gérer les commandes'
            ],
            [
                'id' => 'kitchen',
                'name' => 'Gestion Cuisine',
                'category' => 'Operations',
                'description' => 'Accès aux outils de cuisine'
            ],
            [
                'id' => 'menu',
                'name' => 'Gestion Menu',
                'category' => 'Operations',
                'description' => 'Modifier les plats et menus'
            ],
            [
                'id' => 'inventory',
                'name' => 'Gestion Stock',
                'category' => 'Operations',
                'description' => 'Gérer le stock et les ingrédients'
            ],
            [
                'id' => 'customers',
                'name' => 'Gestion Clients',
                'category' => 'Service',
                'description' => 'Gérer les profils clients'
            ],
            [
                'id' => 'reports',
                'name' => 'Rapports',
                'category' => 'Analytics',
                'description' => 'Accès aux rapports et analyses'
            ],
            [
                'id' => 'settings',
                'name' => 'Paramètres',
                'category' => 'Administration',
                'description' => 'Modifier les paramètres système'
            ],
            [
                'id' => 'system',
                'name' => 'Administration Système',
                'category' => 'Administration',
                'description' => 'Accès complet au système'
            ],
            [
                'id' => 'support',
                'name' => 'Support Client',
                'category' => 'Service',
                'description' => 'Gérer le support et les tickets'
            ],
            [
                'id' => 'edit_admin',
                'name' => 'Modifier Administrateurs',
                'category' => 'Administration',
                'description' => 'Modifier les utilisateurs avec rôle ADMIN'
            ],
            [
                'id' => 'edit_super_admin',
                'name' => 'Modifier Super Administrateurs',
                'category' => 'Administration',
                'description' => 'Modifier les utilisateurs avec rôle SUPER_ADMIN'
            ]
        ];

        return new JsonResponse($permissions);
    }

    #[Route('/api/admin/current-user-permissions', name: 'api_admin_current_user_permissions', methods: ['GET'])]
    public function getCurrentUserPermissions(): JsonResponse
    {
        try {
            /** @var User $currentUser */
            $currentUser = $this->getUser();
            $adminProfile = $currentUser->getAdminProfile();
            
            if (!$adminProfile) {
                return new JsonResponse([
                    'success' => true,
                    'permissions' => [],
                    'roles' => $currentUser->getRoles()
                ]);
            }

            return new JsonResponse([
                'success' => true,
                'permissions' => $adminProfile->getPermissionsAvancees() ?? [],
                'roles' => $currentUser->getRoles(),
                'internal_roles' => $adminProfile->getRolesInternes() ?? []
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
                    'can_edit' => $this->canEditUser($currentUser, $user),
                    'admin_profile' => $adminProfile ? [
                        'id' => $adminProfile->getId(),
                        'roles_internes' => $adminProfile->getRolesInternes(),
                        'permissions_avancees' => $adminProfile->getPermissionsAvancees(),
                        'notes_interne' => $adminProfile->getNotesInterne(),
                        'created_at' => $adminProfile->getCreatedAt()->format('Y-m-d H:i:s'),
                        'updated_at' => $adminProfile->getUpdatedAt()->format('Y-m-d H:i:s')
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

    /**
     * Check if current user can edit target user based on advanced permissions
     */
    private function canEditUser(User $currentUser, User $targetUser): bool
    {
        $currentProfile = $currentUser->getAdminProfile();
        if (!$currentProfile) {
            return false;
        }

        $permissions = $currentProfile->getPermissionsAvancees() ?? [];
        $targetRoles = $targetUser->getRoles();

        // Check if target user is SUPER_ADMIN
        if (in_array('ROLE_SUPER_ADMIN', $targetRoles)) {
            return in_array('edit_super_admin', $permissions);
        }
        
        // Check if target user is ADMIN
        if (in_array('ROLE_ADMIN', $targetRoles)) {
            return in_array('edit_admin', $permissions) || in_array('edit_super_admin', $permissions);
        }

        return false;
    }

    /**
     * Get default permissions based on user roles
     */
    private function getDefaultPermissionsForRole(array $roles): array
    {
        $permissions = [];

        if (in_array('ROLE_SUPER_ADMIN', $roles)) {
            // Super admin gets all permissions
            $permissions = [
                'dashboard', 'users', 'orders', 'kitchen', 'menu', 'inventory',
                'customers', 'reports', 'settings', 'system', 'support',
                'edit_admin', 'edit_super_admin'
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

            // Check permissions - current user must be able to edit target user
            /** @var User $currentUser */
            $currentUser = $this->getUser();
            if (!$this->canEditUser($currentUser, $user)) {
                return new JsonResponse([
                    'error' => 'Permissions insuffisantes',
                    'message' => 'Vous n\'avez pas les permissions nécessaires pour modifier cet utilisateur.',
                    'type' => 'permission_denied'
                ], 403);
            }
            
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
            
            // Update admin profile data
            if (isset($data['roles_internes'])) {
                $adminProfile->setRolesInternes($data['roles_internes']);
            }
            if (isset($data['permissions_avancees'])) {
                $adminProfile->setPermissionsAvancees($data['permissions_avancees']);
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
} 