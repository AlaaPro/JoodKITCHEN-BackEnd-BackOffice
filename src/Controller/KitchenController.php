<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\KitchenProfile;
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

class KitchenController extends AbstractController
{
    public function __construct(
        private PermissionService $permissionService
    ) {}

    #[Route('/api/kitchen/create-staff', name: 'api_kitchen_create_staff', methods: ['POST'])]
    #[IsGranted('manage_kitchen_staff')]
    public function createKitchenStaff(
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
            $requiredFields = ['email', 'password', 'nom', 'prenom', 'poste_cuisine'];
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
            $user->setRoles($data['roles'] ?? ['ROLE_KITCHEN']);
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
            
            // Create KitchenProfile
            $kitchenProfile = new KitchenProfile();
            $kitchenProfile->setUser($user);
            $kitchenProfile->setPosteCuisine($data['poste_cuisine']);
            
            // Set kitchen-specific fields
            if (!empty($data['disponibilite'])) {
                $kitchenProfile->setDisponibilite($data['disponibilite']);
            }
            if (!empty($data['specialites']) && is_array($data['specialites'])) {
                $kitchenProfile->setSpecialites($data['specialites']);
            }
            if (!empty($data['certifications']) && is_array($data['certifications'])) {
                $kitchenProfile->setCertifications($data['certifications']);
            }
            if (!empty($data['horaire_travail']) && is_array($data['horaire_travail'])) {
                $kitchenProfile->setHoraireTravail($data['horaire_travail']);
            }
            if (!empty($data['experience_annees'])) {
                $kitchenProfile->setExperienceAnnees((int)$data['experience_annees']);
            }
            if (!empty($data['salaire_horaire'])) {
                $kitchenProfile->setSalaireHoraire($data['salaire_horaire']);
            }
            if (!empty($data['heures_par_semaine'])) {
                $kitchenProfile->setHeuresParSemaine((int)$data['heures_par_semaine']);
            }
            if (!empty($data['date_embauche'])) {
                $kitchenProfile->setDateEmbauche(new \DateTime($data['date_embauche']));
            }
            if (!empty($data['statut_travail'])) {
                $kitchenProfile->setStatutTravail($data['statut_travail']);
            }
            
            // Set kitchen permissions if provided (using normalized approach)
            if (!empty($data['permissions_kitchen']) && is_array($data['permissions_kitchen'])) {
                // Keep JSON for backward compatibility
                $kitchenProfile->setPermissionsKitchen($data['permissions_kitchen']);
                
                // Also set normalized permissions
                foreach ($data['permissions_kitchen'] as $permissionName) {
                    $permission = $entityManager->getRepository(Permission::class)->findOneBy(['name' => $permissionName]);
                    if ($permission) {
                        $kitchenProfile->addPermission($permission);
                    }
                }
            } else {
                // Auto-assign default permissions based on position
                $defaultPermissions = $this->getDefaultPermissionsForPosition($data['poste_cuisine']);
                $kitchenProfile->setPermissionsKitchen($defaultPermissions);
                
                // Also set normalized permissions
                foreach ($defaultPermissions as $permissionName) {
                    $permission = $entityManager->getRepository(Permission::class)->findOneBy(['name' => $permissionName]);
                    if ($permission) {
                        $kitchenProfile->addPermission($permission);
                    }
                }
            }
            
            // Set internal notes if provided
            if (!empty($data['notes_interne'])) {
                $kitchenProfile->setNotesInterne(trim($data['notes_interne']));
            }
            
            // Validate kitchen profile
            $profileErrors = $validator->validate($kitchenProfile);
            if (count($profileErrors) > 0) {
                $errorMessages = [];
                foreach ($profileErrors as $error) {
                    $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
                }
                return new JsonResponse([
                    'error' => 'Erreurs de validation du profil cuisine',
                    'message' => 'Les données du profil cuisine ne sont pas valides.',
                    'details' => $errorMessages,
                    'type' => 'validation_error'
                ], 400);
            }
            
            // Save both entities in transaction
            $entityManager->beginTransaction();
            try {
                $entityManager->persist($user);
                $entityManager->persist($kitchenProfile);
                $entityManager->flush();
                $entityManager->commit();
                
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Personnel de cuisine créé avec succès',
                    'type' => 'success',
                    'user' => [
                        'id' => $user->getId(),
                        'nom' => $user->getNom(),
                        'prenom' => $user->getPrenom(),
                        'email' => $user->getEmail(),
                        'telephone' => $user->getTelephone(),
                        'roles' => $user->getRoles(),
                        'is_active' => $user->getIsActive(),
                        'photo_profil' => $user->getPhotoProfil(),
                        'photo_profil_url' => $user->getPhotoProfilUrl(),
                        'created_at' => $user->getCreatedAt()->format('Y-m-d H:i:s')
                    ],
                    'kitchen_profile' => [
                        'id' => $kitchenProfile->getId(),
                        'poste_cuisine' => $kitchenProfile->getPosteCuisine(),
                        'specialites' => $kitchenProfile->getSpecialites(),
                        'experience_annees' => $kitchenProfile->getExperienceAnnees(),
                        'statut_travail' => $kitchenProfile->getStatutTravail(),
                        'permissions_kitchen' => $kitchenProfile->getPermissionsKitchen(),
                        'notes_interne' => $kitchenProfile->getNotesInterne()
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

    #[Route('/api/kitchen/positions', name: 'api_kitchen_positions', methods: ['GET'])]
    #[IsGranted('view_kitchen')]
    public function getKitchenPositions(): JsonResponse
    {
        $positions = [
            'chef_executif' => [
                'name' => 'Chef Exécutif',
                'description' => 'Responsable de toute la cuisine',
                'level' => 5
            ],
            'chef_cuisine' => [
                'name' => 'Chef de Cuisine',
                'description' => 'Gestion d\'une cuisine spécifique',
                'level' => 4
            ],
            'sous_chef' => [
                'name' => 'Sous Chef',
                'description' => 'Assistant du chef de cuisine',
                'level' => 3
            ],
            'cuisinier' => [
                'name' => 'Cuisinier',
                'description' => 'Préparation des plats',
                'level' => 2
            ],
            'commis' => [
                'name' => 'Commis de Cuisine',
                'description' => 'Assistant cuisinier',
                'level' => 1
            ],
            'plongeur' => [
                'name' => 'Plongeur',
                'description' => 'Nettoyage et entretien',
                'level' => 0
            ]
        ];

        return new JsonResponse([
            'success' => true,
            'data' => $positions
        ]);
    }

    #[Route('/api/kitchen/specialties', name: 'api_kitchen_specialties', methods: ['GET'])]
    #[IsGranted('view_kitchen')]
    public function getKitchenSpecialties(): JsonResponse
    {
        $specialties = [
            'marocain' => 'Cuisine Marocaine',
            'italien' => 'Cuisine Italienne', 
            'international' => 'Cuisine Internationale',
            'polyvalent' => 'Polyvalent (Toutes cuisines)',
            'patisserie' => 'Pâtisserie',
            'grillade' => 'Grillades',
            'pizza' => 'Pizzas',
            'salade' => 'Salades et Entrées',
            'dessert' => 'Desserts'
        ];

        return new JsonResponse([
            'success' => true,
            'data' => $specialties
        ]);
    }

    #[Route('/api/kitchen/permissions', name: 'api_kitchen_permissions', methods: ['GET'])]
    #[IsGranted('view_permissions')]
    public function getKitchenPermissions(): JsonResponse
    {
        $permissions = [
            'kitchen' => [
                'manage_kitchen' => 'Gestion de la cuisine',
                'view_kitchen_dashboard' => 'Voir le tableau de bord cuisine',
                'manage_orders' => 'Gestion des commandes',
                'update_order_status' => 'Mettre à jour le statut des commandes',
                'view_preparation_queue' => 'Voir la file de préparation'
            ],
            'menu' => [
                'view_daily_menu' => 'Voir le menu du jour',
                'suggest_menu_changes' => 'Suggérer des changements au menu'
            ],
            'inventory' => [
                'view_inventory' => 'Voir l\'inventaire',
                'update_ingredient_usage' => 'Mettre à jour l\'utilisation des ingrédients'
            ],
            'team' => [
                'view_kitchen_team' => 'Voir l\'équipe cuisine',
                'manage_kitchen_staff' => 'Gérer le personnel cuisine'
            ]
        ];

        return new JsonResponse([
            'success' => true,
            'data' => $permissions
        ]);
    }

    #[Route('/api/kitchen/staff', name: 'api_kitchen_staff', methods: ['GET'])]
    #[IsGranted('view_kitchen')]
    public function getKitchenStaff(EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $users = $entityManager->getRepository(User::class)
                ->createQueryBuilder('u')
                ->leftJoin('u.kitchenProfile', 'kp')
                ->addSelect('kp')
                ->where('kp.id IS NOT NULL')
                ->orderBy('u.nom', 'ASC')
                ->getQuery()
                ->getResult();

            $staffData = [];
            foreach ($users as $user) {
                $profile = $user->getKitchenProfile();
                
                $staffData[] = [
                    'id' => $user->getId(),
                    'nom' => $user->getNom(),
                    'prenom' => $user->getPrenom(),
                    'email' => $user->getEmail(),
                    'telephone' => $user->getTelephone(),
                    'roles' => $user->getRoles(),
                    'is_active' => $user->getIsActive(),
                    'photo_profil' => $user->getPhotoProfil(),
                    'photo_profil_url' => $user->getPhotoProfilUrl(),
                    'last_connexion' => $user->getLastConnexion()?->format('Y-m-d H:i:s'),
                    'created_at' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
                    'kitchen_profile' => $profile ? [
                        'id' => $profile->getId(),
                        'poste_cuisine' => $profile->getPosteCuisine(),
                        'specialites' => $profile->getSpecialites(),
                        'certifications' => $profile->getCertifications(),
                        'horaire_travail' => $profile->getHoraireTravail(),
                        'permissions_kitchen' => $profile->getPermissionsKitchen(),
                        'statut_travail' => $profile->getStatutTravail(),
                        'experience_annees' => $profile->getExperienceAnnees(),
                        'salaire_horaire' => $profile->getSalaireHoraire(),
                        'heures_par_semaine' => $profile->getHeuresParSemaine(),
                        'date_embauche' => $profile->getDateEmbauche()?->format('Y-m-d'),
                        'notes_interne' => $profile->getNotesInterne(),
                        'experience_formatted' => $profile->getExperienceFormatted(),
                        'statut_color' => $profile->getStatutColor(),
                        'position_color' => $profile->getPositionColor(),
                        'is_available' => $profile->isAvailable(),
                        'created_at' => $profile->getCreatedAt()->format('Y-m-d H:i:s'),
                        'updated_at' => $profile->getUpdatedAt()->format('Y-m-d H:i:s')
                    ] : null
                ];
            }

            // Calculate statistics
            $stats = [
                'total' => count($staffData),
                'actif' => count(array_filter($staffData, fn($s) => $s['kitchen_profile'] && $s['kitchen_profile']['statut_travail'] === 'actif')),
                'pause' => count(array_filter($staffData, fn($s) => $s['kitchen_profile'] && $s['kitchen_profile']['statut_travail'] === 'pause')),
                'absent' => count(array_filter($staffData, fn($s) => $s['kitchen_profile'] && $s['kitchen_profile']['statut_travail'] === 'absent')),
                'conge' => count(array_filter($staffData, fn($s) => $s['kitchen_profile'] && $s['kitchen_profile']['statut_travail'] === 'conge')),
                'by_position' => []
            ];

            // Calculate position distribution
            foreach ($staffData as $staff) {
                if ($staff['kitchen_profile']) {
                    $position = $staff['kitchen_profile']['poste_cuisine'];
                    $stats['by_position'][$position] = ($stats['by_position'][$position] ?? 0) + 1;
                }
            }

            return new JsonResponse([
                'success' => true,
                'data' => $staffData,
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors du chargement',
                'message' => 'Impossible de charger la liste du personnel.',
                'type' => 'server_error',
                'debug' => $this->getParameter('kernel.environment') === 'dev' ? $e->getMessage() : null
            ], 500);
        }
    }

    #[Route('/api/kitchen/update-staff/{id}', name: 'api_kitchen_update_staff', methods: ['PUT'])]
    #[IsGranted('manage_kitchen_staff')]
    public function updateKitchenStaff(
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

            $user = $entityManager->getRepository(User::class)->find($id);
            if (!$user || !$user->getKitchenProfile()) {
                return new JsonResponse([
                    'error' => 'Personnel non trouvé',
                    'message' => 'Le personnel de cuisine demandé n\'existe pas.',
                    'type' => 'not_found'
                ], 404);
            }

            $kitchenProfile = $user->getKitchenProfile();

            // Update user fields
            if (isset($data['nom'])) {
                $user->setNom(trim($data['nom']));
            }
            if (isset($data['prenom'])) {
                $user->setPrenom(trim($data['prenom']));
            }
            if (isset($data['email'])) {
                // Check for email uniqueness
                $existingUser = $entityManager->getRepository(User::class)
                    ->createQueryBuilder('u')
                    ->where('u.email = :email')
                    ->andWhere('u.id != :id')
                    ->setParameter('email', strtolower(trim($data['email'])))
                    ->setParameter('id', $id)
                    ->getQuery()
                    ->getOneOrNullResult();
                
                if ($existingUser) {
                    return new JsonResponse([
                        'error' => 'Email déjà utilisé',
                        'message' => 'Un autre utilisateur utilise déjà cet email.',
                        'type' => 'duplicate_email'
                    ], 409);
                }
                
                $user->setEmail(strtolower(trim($data['email'])));
            }
            if (isset($data['telephone'])) {
                $user->setTelephone($data['telephone']);
            }
            if (isset($data['is_active'])) {
                $user->setIsActive((bool)$data['is_active']);
            }
            if (isset($data['password']) && !empty($data['password'])) {
                $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
                $user->setPassword($hashedPassword);
            }

            // Update kitchen profile fields
            if (isset($data['poste_cuisine'])) {
                $kitchenProfile->setPosteCuisine($data['poste_cuisine']);
            }
            if (isset($data['disponibilite'])) {
                $kitchenProfile->setDisponibilite($data['disponibilite']);
            }
            if (isset($data['specialites'])) {
                $kitchenProfile->setSpecialites($data['specialites']);
            }
            if (isset($data['certifications'])) {
                $kitchenProfile->setCertifications($data['certifications']);
            }
            if (isset($data['horaire_travail'])) {
                $kitchenProfile->setHoraireTravail($data['horaire_travail']);
            }
            if (isset($data['statut_travail'])) {
                $kitchenProfile->setStatutTravail($data['statut_travail']);
            }
            if (isset($data['experience_annees'])) {
                $kitchenProfile->setExperienceAnnees((int)$data['experience_annees']);
            }
            if (isset($data['salaire_horaire'])) {
                $kitchenProfile->setSalaireHoraire($data['salaire_horaire']);
            }
            if (isset($data['heures_par_semaine'])) {
                $kitchenProfile->setHeuresParSemaine((int)$data['heures_par_semaine']);
            }
            if (isset($data['date_embauche'])) {
                $kitchenProfile->setDateEmbauche(new \DateTime($data['date_embauche']));
            }
            if (isset($data['notes_interne'])) {
                $kitchenProfile->setNotesInterne($data['notes_interne']);
            }
            if (isset($data['permissions_kitchen'])) {
                $kitchenProfile->setPermissionsKitchen($data['permissions_kitchen']);
            }

            // Validate both entities
            $userErrors = $validator->validate($user);
            $profileErrors = $validator->validate($kitchenProfile);
            
            if (count($userErrors) > 0 || count($profileErrors) > 0) {
                $errorMessages = [];
                foreach ($userErrors as $error) {
                    $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
                }
                foreach ($profileErrors as $error) {
                    $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
                }
                return new JsonResponse([
                    'error' => 'Erreurs de validation',
                    'message' => 'Les données saisies ne sont pas valides.',
                    'details' => $errorMessages,
                    'type' => 'validation_error'
                ], 400);
            }

            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Personnel mis à jour avec succès',
                'type' => 'success',
                'user' => [
                    'id' => $user->getId(),
                    'nom' => $user->getNom(),
                    'prenom' => $user->getPrenom(),
                    'email' => $user->getEmail(),
                    'telephone' => $user->getTelephone(),
                    'is_active' => $user->getIsActive()
                ],
                'kitchen_profile' => [
                    'id' => $kitchenProfile->getId(),
                    'poste_cuisine' => $kitchenProfile->getPosteCuisine(),
                    'specialites' => $kitchenProfile->getSpecialites(),
                    'statut_travail' => $kitchenProfile->getStatutTravail(),
                    'experience_annees' => $kitchenProfile->getExperienceAnnees(),
                    'permissions_kitchen' => $kitchenProfile->getPermissionsKitchen()
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de la mise à jour',
                'message' => 'Une erreur inattendue s\'est produite. Veuillez réessayer.',
                'type' => 'server_error',
                'debug' => $this->getParameter('kernel.environment') === 'dev' ? $e->getMessage() : null
            ], 500);
        }
    }

    #[Route('/api/kitchen/delete-staff/{id}', name: 'api_kitchen_delete_staff', methods: ['DELETE'])]
    #[IsGranted('delete_kitchen_staff')]
    public function deleteKitchenStaff(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $user = $entityManager->getRepository(User::class)->find($id);
            
            if (!$user || !$user->getKitchenProfile()) {
                return new JsonResponse([
                    'error' => 'Personnel non trouvé',
                    'message' => 'Le personnel de cuisine demandé n\'existe pas.',
                    'type' => 'not_found'
                ], 404);
            }

            $entityManager->remove($user);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Personnel supprimé avec succès',
                'type' => 'success'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de la suppression',
                'message' => 'Une erreur inattendue s\'est produite. Veuillez réessayer.',
                'type' => 'server_error',
                'debug' => $this->getParameter('kernel.environment') === 'dev' ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get default permissions based on kitchen position
     */
    private function getDefaultPermissionsForPosition(string $position): array
    {
        return match($position) {
            'chef_executif' => [
                'manage_kitchen', 'view_kitchen_dashboard', 'manage_orders', 
                'update_order_status', 'view_preparation_queue', 'manage_kitchen_staff',
                'view_inventory', 'update_ingredient_usage'
            ],
            'chef_cuisine' => [
                'view_kitchen_dashboard', 'manage_orders', 'update_order_status',
                'view_preparation_queue', 'view_inventory', 'update_ingredient_usage'
            ],
            'sous_chef' => [
                'view_kitchen_dashboard', 'update_order_status', 'view_preparation_queue',
                'view_inventory', 'update_ingredient_usage'
            ],
            'cuisinier' => [
                'view_kitchen_dashboard', 'update_order_status', 'view_preparation_queue'
            ],
            'commis' => [
                'view_kitchen_dashboard', 'view_preparation_queue'
            ],
            'plongeur' => [
                'view_kitchen_dashboard'
            ],
            default => ['view_kitchen_dashboard']
        };
    }
} 