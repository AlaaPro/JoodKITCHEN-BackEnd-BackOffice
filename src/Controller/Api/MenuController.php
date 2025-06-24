<?php

namespace App\Controller\Api;

use App\Entity\Category;
use App\Entity\Plat;
use App\Entity\Menu;
use App\Entity\MenuPlat;
use App\Repository\CategoryRepository;
use App\Repository\PlatRepository;
use App\Repository\MenuRepository;
use App\Repository\MenuPlatRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Vich\UploaderBundle\Storage\StorageInterface;

#[Route('/api/admin/menu')]
#[IsGranted('ROLE_ADMIN')]
class MenuController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private CategoryRepository $categoryRepository,
        private PlatRepository $platRepository,
        private MenuRepository $menuRepository,
        private MenuPlatRepository $menuPlatRepository,
        private StorageInterface $storage
    ) {}

    // ========================
    // CATEGORIES CRUD
    // ========================

    #[Route('/categories', name: 'api_admin_categories', methods: ['GET'])]
    public function getCategories(): JsonResponse
    {
        $categories = $this->categoryRepository->findHierarchicalCategories();
        
        $data = [];
        foreach ($categories as $category) {
            $categoryData = [
                'id' => $category->getId(),
                'nom' => $category->getNom(),
                'description' => $category->getDescription(),
                'icon' => $category->getIcon(),
                'couleur' => $category->getCouleur(),
                'position' => $category->getPosition(),
                'actif' => $category->getActif(),
                'visible' => $category->getVisible(),
                'parent' => $category->getParent() ? $category->getParent()->getId() : null,
                'dishCount' => $category->getTotalPlatsCount(),
                'fullPath' => $category->getFullPath(),
                'isRoot' => $category->isRootCategory(),
                'sousCategories' => []
            ];
            
            foreach ($category->getSousCategories() as $subCategory) {
                $categoryData['sousCategories'][] = [
                    'id' => $subCategory->getId(),
                    'nom' => $subCategory->getNom(),
                    'description' => $subCategory->getDescription(),
                    'icon' => $subCategory->getIcon(),
                    'couleur' => $subCategory->getCouleur(),
                    'position' => $subCategory->getPosition(),
                    'actif' => $subCategory->getActif(),
                    'visible' => $subCategory->getVisible(),
                    'dishCount' => $subCategory->getTotalPlatsCount()
                ];
            }
            
            $data[] = $categoryData;
        }
        
        return $this->json([
            'success' => true,
            'data' => $data,
            'count' => count($data)
        ]);
    }

    #[Route('/categories', name: 'api_admin_categories_create', methods: ['POST'])]
    public function createCategory(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $category = new Category();
        $category->setNom($data['nom'] ?? '');
        $category->setDescription($data['description'] ?? null);
        $category->setIcon($data['icon'] ?? null);
        $category->setCouleur($data['couleur'] ?? null);
        $category->setActif($data['actif'] ?? true);
        $category->setVisible($data['visible'] ?? true);
        
        // Handle parent category
        if (!empty($data['parentId'])) {
            $parent = $this->categoryRepository->find($data['parentId']);
            if ($parent) {
                $category->setParent($parent);
                $category->setPosition($this->categoryRepository->findNextPosition($parent));
            }
        } else {
            $category->setPosition($this->categoryRepository->findNextPosition());
        }
        
        $errors = $this->validator->validate($category);
        if (count($errors) > 0) {
            return $this->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => (string) $errors
            ], 400);
        }
        
        $this->entityManager->persist($category);
        $this->entityManager->flush();
        
        return $this->json([
            'success' => true,
            'message' => 'Category created successfully',
            'data' => [
                'id' => $category->getId(),
                'nom' => $category->getNom(),
                'description' => $category->getDescription(),
                'position' => $category->getPosition()
            ]
        ], 201);
    }

    #[Route('/categories/reorder', name: 'api_admin_categories_reorder', methods: ['PUT'])]
    public function reorderCategories(Request $request): JsonResponse
    {
        try {
            // Debug: Log the raw request content
            $rawContent = $request->getContent();
            error_log('🔍 Reorder request raw content: ' . $rawContent);
            
            $data = json_decode($rawContent, true);
            error_log('🔍 Decoded JSON data: ' . print_r($data, true));
            
            $positions = $data['positions'] ?? [];
            error_log('🔍 Positions array: ' . print_r($positions, true));
            
            if (empty($positions)) {
                return $this->json([
                    'success' => false,
                    'message' => 'No positions data provided'
                ], 400);
            }
            
            // Validate positions data
            foreach ($positions as $categoryId => $position) {
                if (!is_numeric($categoryId) || !is_numeric($position)) {
                    error_log('❌ Invalid position data: ID=' . $categoryId . ', Position=' . $position);
                    return $this->json([
                        'success' => false,
                        'message' => 'Invalid position data format'
                    ], 400);
                }
            }
            
            error_log('✅ Starting updatePositions with valid data');
            $this->categoryRepository->updatePositions($positions);
            error_log('✅ updatePositions completed successfully');
            
            return $this->json([
                'success' => true,
                'message' => 'Categories reordered successfully'
            ]);
        } catch (\Exception $e) {
            error_log('💥 Reorder error: ' . $e->getMessage());
            error_log('💥 Reorder error trace: ' . $e->getTraceAsString());
            
            return $this->json([
                'success' => false,
                'message' => 'Error reordering categories: ' . $e->getMessage(),
                'debug' => [
                    'line' => $e->getLine(),
                    'file' => basename($e->getFile())
                ]
            ], 500);
        }
    }

    #[Route('/categories/{id}', name: 'api_admin_categories_update', methods: ['PUT'])]
    public function updateCategory(int $id, Request $request): JsonResponse
    {
        $category = $this->categoryRepository->find($id);
        if (!$category) {
            return $this->json(['success' => false, 'message' => 'Category not found'], 404);
        }
        
        $data = json_decode($request->getContent(), true);
        
        $category->setNom($data['nom'] ?? $category->getNom());
        $category->setDescription($data['description'] ?? $category->getDescription());
        $category->setIcon($data['icon'] ?? $category->getIcon());
        $category->setCouleur($data['couleur'] ?? $category->getCouleur());
        $category->setActif($data['actif'] ?? $category->getActif());
        $category->setVisible($data['visible'] ?? $category->getVisible());
        
        // Handle parent category change
        if (isset($data['parentId'])) {
            if (!empty($data['parentId'])) {
                $parent = $this->categoryRepository->find($data['parentId']);
                if ($parent && $parent !== $category) { // Prevent self-assignment
                    $category->setParent($parent);
                }
            } else {
                $category->setParent(null);
            }
        }
        
        $errors = $this->validator->validate($category);
        if (count($errors) > 0) {
            return $this->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => (string) $errors
            ], 400);
        }
        
        $this->entityManager->flush();
        
        return $this->json([
            'success' => true,
            'message' => 'Category updated successfully',
            'data' => [
                'id' => $category->getId(),
                'nom' => $category->getNom(),
                'description' => $category->getDescription()
            ]
        ]);
    }

    #[Route('/categories/{id}', name: 'api_admin_categories_delete', methods: ['DELETE'])]
    public function deleteCategory(int $id): JsonResponse
    {
        $category = $this->categoryRepository->find($id);
        if (!$category) {
            return $this->json(['success' => false, 'message' => 'Category not found'], 404);
        }
        
        // Check if category has plats
        if ($category->getPlats()->count() > 0) {
            return $this->json([
                'success' => false,
                'message' => 'Cannot delete category with plats. Move plats to another category first.'
            ], 400);
        }
        
        // Check if category has subcategories
        if ($category->getSousCategories()->count() > 0) {
            return $this->json([
                'success' => false,
                'message' => 'Cannot delete category with subcategories. Delete subcategories first.'
            ], 400);
        }
        
        $this->entityManager->remove($category);
        $this->entityManager->flush();
        
        return $this->json([
            'success' => true,
            'message' => 'Category deleted successfully'
        ]);
    }

    // ========================
    // PLATS CRUD
    // ========================

    #[Route('/plats', name: 'api_admin_plats', methods: ['GET'])]
    public function getPlats(Request $request): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 20);
        $category = $request->query->get('category');
        $status = $request->query->get('status');
        $search = $request->query->get('search');
        $minPrice = $request->query->get('minPrice');
        $maxPrice = $request->query->get('maxPrice');
        $popular = $request->query->getBoolean('popular');
        $vegetarian = $request->query->getBoolean('vegetarian');
        
        $queryBuilder = $this->platRepository->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->addSelect('c');
        
        // Apply filters
        if ($category) {
            $queryBuilder->andWhere('c.id = :category')
                        ->setParameter('category', $category);
        }
        
        if ($status) {
            switch ($status) {
                case 'available':
                    $queryBuilder->andWhere('p.disponible = true');
                    break;
                case 'unavailable':
                case 'out_of_stock':
                    $queryBuilder->andWhere('p.disponible = false');
                    break;
            }
        }
        
        if ($search) {
            $queryBuilder->andWhere('p.nom LIKE :search OR p.description LIKE :search')
                        ->setParameter('search', '%' . $search . '%');
        }
        
        if ($minPrice !== null && $minPrice !== '') {
            $queryBuilder->andWhere('p.prix >= :minPrice')
                        ->setParameter('minPrice', (float)$minPrice);
        }
        
        if ($maxPrice !== null && $maxPrice !== '') {
            $queryBuilder->andWhere('p.prix <= :maxPrice')
                        ->setParameter('maxPrice', (float)$maxPrice);
        }
        
        if ($popular) {
            $queryBuilder->andWhere('p.populaire = true');
        }
        
        if ($vegetarian) {
            $queryBuilder->andWhere('p.vegetarien = true');
        }
        
        $total = count($queryBuilder->getQuery()->getResult());
        
        $plats = $queryBuilder
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->orderBy('p.nom', 'ASC')
            ->getQuery()
            ->getResult();
        
        $data = [];
        foreach ($plats as $plat) {
            $data[] = [
                'id' => $plat->getId(),
                'nom' => $plat->getNom(),
                'description' => $plat->getDescription(),
                'prix' => $plat->getPrix(),
                'category' => $plat->getCategory() ? [
                    'id' => $plat->getCategory()->getId(),
                    'nom' => $plat->getCategory()->getNom()
                ] : null,
                'image' => $this->storage->resolveUri($plat, 'imageFile'),
                'disponible' => $plat->getDisponible(),
                'allergenes' => $plat->getAllergenes(),
                'tempsPreparation' => $plat->getTempsPreparation(),
                'createdAt' => $plat->getCreatedAt() ? $plat->getCreatedAt()->format('Y-m-d H:i') : null,
                'updatedAt' => $plat->getUpdatedAt() ? $plat->getUpdatedAt()->format('Y-m-d H:i') : null,
                'populaire' => $plat->isPopulaire(),
                'vegetarien' => $plat->isVegetarien(),
            ];
        }
        
        $stats = [
            'total' => $total,
            'available' => $this->platRepository->count(['disponible' => true]),
            'unavailable' => $this->platRepository->count(['disponible' => false]),
            'averagePrice' => $this->platRepository->getAveragePrice(),
        ];

        return $this->json([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ],
            'stats' => $stats
        ]);
    }

    #[Route('/plats', name: 'api_admin_plats_create', methods: ['POST'])]
    public function createPlat(Request $request): JsonResponse
    {
        // Handle both JSON and FormData
        if ($request->getContentType() === 'json') {
            $data = json_decode($request->getContent(), true);
        } else {
            // FormData
            $data = $request->request->all();
        }
        
        $plat = new Plat();
        $plat->setNom($data['nom'] ?? '');
        $plat->setDescription($data['description'] ?? null);
        $plat->setPrix($data['prix'] ?? '0.00');
        $plat->setDisponible(isset($data['disponible']) ? (bool)$data['disponible'] : true);
        $plat->setAllergenes($data['allergenes'] ?? null);
        $plat->setTempsPreparation($data['tempsPreparation'] ? (int)$data['tempsPreparation'] : null);
        $plat->setPopulaire(isset($data['populaire']) ? (bool)$data['populaire'] : false);
        $plat->setVegetarien(isset($data['vegetarien']) ? (bool)$data['vegetarien'] : false);
        
        // Handle category
        if (!empty($data['categoryId'])) {
            $category = $this->categoryRepository->find($data['categoryId']);
            if ($category) {
                $plat->setCategory($category);
            }
        }
        
        $errors = $this->validator->validate($plat);
        if (count($errors) > 0) {
            return $this->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => (string) $errors
            ], 400);
        }
        
        $this->entityManager->persist($plat);
        $this->entityManager->flush();
        
        return $this->json([
            'success' => true,
            'message' => 'Plat created successfully',
            'data' => [
                'id' => $plat->getId(),
                'nom' => $plat->getNom(),
                'prix' => $plat->getPrix()
            ]
        ], 201);
    }

    #[Route('/plats/{id}', name: 'api_admin_plats_get', methods: ['GET'])]
    public function getPlat(int $id): JsonResponse
    {
        $plat = $this->platRepository->find($id);
        if (!$plat) {
            return $this->json(['success' => false, 'message' => 'Plat not found'], 404);
        }
        
        $data = [
            'id' => $plat->getId(),
            'nom' => $plat->getNom(),
            'description' => $plat->getDescription(),
            'prix' => $plat->getPrix(),
            'category' => $plat->getCategory() ? [
                'id' => $plat->getCategory()->getId(),
                'nom' => $plat->getCategory()->getNom()
            ] : null,
            'image' => $this->storage->resolveUri($plat, 'imageFile'),
            'disponible' => $plat->getDisponible(),
            'allergenes' => $plat->getAllergenes(),
            'tempsPreparation' => $plat->getTempsPreparation(),
            'createdAt' => $plat->getCreatedAt() ? $plat->getCreatedAt()->format('Y-m-d H:i') : null,
            'updatedAt' => $plat->getUpdatedAt() ? $plat->getUpdatedAt()->format('Y-m-d H:i') : null,
            'populaire' => $plat->isPopulaire(),
            'vegetarien' => $plat->isVegetarien(),
        ];

        return $this->json(['success' => true, 'data' => $data]);
    }

    #[Route('/plats/{id}', name: 'api_admin_plats_update', methods: ['PUT', 'PATCH'])]
    public function updatePlat(int $id, Request $request): JsonResponse
    {
        $plat = $this->platRepository->find($id);
        if (!$plat) {
            return $this->json(['success' => false, 'message' => 'Plat not found'], 404);
        }
        
        $data = json_decode($request->getContent(), true);
        
        $plat->setNom($data['nom'] ?? $plat->getNom());
        $plat->setDescription($data['description'] ?? $plat->getDescription());
        $plat->setPrix($data['prix'] ?? $plat->getPrix());
        $plat->setDisponible($data['disponible'] ?? $plat->getDisponible());
        $plat->setAllergenes($data['allergenes'] ?? $plat->getAllergenes());
        $plat->setTempsPreparation($data['tempsPreparation'] ?? $plat->getTempsPreparation());
        $plat->setPopulaire($data['populaire'] ?? $plat->isPopulaire());
        $plat->setVegetarien($data['vegetarien'] ?? $plat->isVegetarien());
        
        // Handle category change
        if (isset($data['categoryId'])) {
            if (!empty($data['categoryId'])) {
                $category = $this->categoryRepository->find($data['categoryId']);
                if ($category) {
                    $plat->setCategory($category);
                }
            } else {
                $plat->setCategory(null);
            }
        }
        
        $errors = $this->validator->validate($plat);
        if (count($errors) > 0) {
            return $this->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => (string) $errors
            ], 400);
        }
        
        $this->entityManager->flush();
        
        return $this->json([
            'success' => true,
            'message' => 'Plat updated successfully',
            'data' => [
                'id' => $plat->getId(),
                'nom' => $plat->getNom(),
                'prix' => $plat->getPrix()
            ]
        ]);
    }

    #[Route('/plats/{id}', name: 'api_admin_plats_delete', methods: ['DELETE'])]
    public function deletePlat(int $id): JsonResponse
    {
        $plat = $this->platRepository->find($id);
        if (!$plat) {
            return $this->json(['success' => false, 'message' => 'Plat not found'], 404);
        }
        
        // Check if plat is used in menus
        if ($plat->getMenuPlats()->count() > 0) {
            return $this->json([
                'success' => false,
                'message' => 'Cannot delete plat that is part of menus. Remove from menus first.'
            ], 400);
        }
        
        $this->entityManager->remove($plat);
        $this->entityManager->flush();
        
        return $this->json([
            'success' => true,
            'message' => 'Plat deleted successfully'
        ]);
    }

    // ========================
    // MENUS CRUD
    // ========================

    #[Route('/menus', name: 'api_admin_menus', methods: ['GET'])]
    public function getMenus(Request $request): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 20);
        $type = $request->query->get('type');
        $tag = $request->query->get('tag');
        $date = $request->query->get('date');
        
        $queryBuilder = $this->menuRepository->createQueryBuilder('m')
            ->leftJoin('m.menuPlats', 'mp')
            ->leftJoin('mp.plat', 'p')
            ->addSelect('mp', 'p');
        
        // Apply filters
        if ($type) {
            $queryBuilder->andWhere('m.type = :type')
                        ->setParameter('type', $type);
        }
        
        if ($tag) {
            $queryBuilder->andWhere('m.tag = :tag')
                        ->setParameter('tag', $tag);
        }
        
        if ($date) {
            $queryBuilder->andWhere('m.date = :date')
                        ->setParameter('date', $date);
        }
        
        $total = count($queryBuilder->getQuery()->getResult());
        
        $menus = $queryBuilder
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->orderBy('m.nom', 'ASC')
            ->getQuery()
            ->getResult();
        
        $data = [];
        foreach ($menus as $menu) {
            $dishes = [];
            foreach ($menu->getMenuPlats() as $menuPlat) {
                $dishes[] = [
                    'id' => $menuPlat->getPlat()->getId(),
                    'nom' => $menuPlat->getPlat()->getNom(),
                    'prix' => $menuPlat->getPlat()->getPrix(),
                    'ordre' => $menuPlat->getOrdre(),
                    'category' => $menuPlat->getPlat()->getCategory() ? [
                        'id' => $menuPlat->getPlat()->getCategory()->getId(),
                        'nom' => $menuPlat->getPlat()->getCategory()->getNom()
                    ] : null
                ];
            }
            
            $data[] = [
                'id' => $menu->getId(),
                'nom' => $menu->getNom(),
                'description' => $menu->getDescription(),
                'type' => $menu->getType(),
                'jourSemaine' => $menu->getJourSemaine(),
                'date' => $menu->getDate()?->format('Y-m-d'),
                'prix' => $menu->getPrix(),
                'tag' => $menu->getTag(),
                'actif' => $menu->getActif(),
                'dishes' => $dishes,
                'dishCount' => count($dishes),
                'createdAt' => $menu->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updatedAt' => $menu->getUpdatedAt()?->format('Y-m-d H:i:s')
            ];
        }
        
        return $this->json([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }

    #[Route('/menus', name: 'api_admin_menus_create', methods: ['POST'])]
    public function createMenu(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->json([
                    'success' => false,
                    'message' => 'Invalid JSON data provided'
                ], 400);
            }
            
            $menu = new Menu();
            $menu->setNom($data['nom'] ?? '');
            $menu->setDescription($data['description'] ?? null);
            $menu->setType($data['type'] ?? 'normal');
            $menu->setJourSemaine($data['jourSemaine'] ?? null);
            $menu->setPrix($data['prix'] ?? '0.00');
            $menu->setTag($data['tag'] ?? null);
            $menu->setActif($data['actif'] ?? true);
            
            if (!empty($data['date'])) {
                try {
                    $menu->setDate(new \DateTime($data['date']));
                } catch (\Exception $e) {
                    return $this->json([
                        'success' => false,
                        'message' => 'Invalid date format: ' . $data['date']
                    ], 400);
                }
            }
            
            $errors = $this->validator->validate($menu);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
                }
                return $this->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $errorMessages
                ], 400);
            }
            
            $this->entityManager->persist($menu);
            $this->entityManager->flush();
            
            // Add dishes to menu
            if (!empty($data['dishes'])) {
                foreach ($data['dishes'] as $dishData) {
                    // Handle both old format (just IDs) and new format (objects with id and ordre)
                    $dishId = is_array($dishData) ? $dishData['id'] : $dishData;
                    $ordre = is_array($dishData) ? ($dishData['ordre'] ?? 1) : 1;
                    
                    $dish = $this->platRepository->find($dishId);
                    if ($dish) {
                        $menuPlat = new MenuPlat();
                        $menuPlat->setMenu($menu);
                        $menuPlat->setPlat($dish);
                        $menuPlat->setOrdre($ordre);
                        
                        $this->entityManager->persist($menuPlat);
                    }
                }
                $this->entityManager->flush();
            }
            
            return $this->json([
                'success' => true,
                'message' => 'Menu created successfully',
                'data' => [
                    'id' => $menu->getId(),
                    'nom' => $menu->getNom(),
                    'type' => $menu->getType(),
                    'tag' => $menu->getTag(),
                    'prix' => $menu->getPrix(),
                    'dishCount' => count($data['dishes'] ?? [])
                ]
            ], 201);
            
        } catch (\Exception $e) {
            error_log('Menu creation error: ' . $e->getMessage());
            return $this->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/menus/{id}', name: 'api_admin_menus_get', methods: ['GET'])]
    public function getMenu(int $id): JsonResponse
    {
        $menu = $this->menuRepository->find($id);
        if (!$menu) {
            return $this->json(['success' => false, 'message' => 'Menu not found'], 404);
        }
        
        $dishes = [];
        foreach ($menu->getMenuPlats() as $menuPlat) {
            $dishes[] = [
                'id' => $menuPlat->getPlat()->getId(),
                'nom' => $menuPlat->getPlat()->getNom(),
                'description' => $menuPlat->getPlat()->getDescription(),
                'prix' => $menuPlat->getPlat()->getPrix(),
                'ordre' => $menuPlat->getOrdre(),
                'category' => $menuPlat->getPlat()->getCategory() ? [
                    'id' => $menuPlat->getPlat()->getCategory()->getId(),
                    'nom' => $menuPlat->getPlat()->getCategory()->getNom()
                ] : null
            ];
        }
        
        return $this->json([
            'success' => true,
            'data' => [
                'id' => $menu->getId(),
                'nom' => $menu->getNom(),
                'description' => $menu->getDescription(),
                'type' => $menu->getType(),
                'jourSemaine' => $menu->getJourSemaine(),
                'date' => $menu->getDate()?->format('Y-m-d'),
                'prix' => $menu->getPrix(),
                'tag' => $menu->getTag(),
                'actif' => $menu->getActif(),
                'dishes' => $dishes,
                'createdAt' => $menu->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updatedAt' => $menu->getUpdatedAt()?->format('Y-m-d H:i:s')
            ]
        ]);
    }

    #[Route('/menus/{id}', name: 'api_admin_menus_update', methods: ['PUT'])]
    public function updateMenu(int $id, Request $request): JsonResponse
    {
        $menu = $this->menuRepository->find($id);
        if (!$menu) {
            return $this->json(['success' => false, 'message' => 'Menu not found'], 404);
        }
        
        $data = json_decode($request->getContent(), true);
        
        $menu->setNom($data['nom'] ?? $menu->getNom());
        $menu->setDescription($data['description'] ?? $menu->getDescription());
        $menu->setType($data['type'] ?? $menu->getType());
        $menu->setJourSemaine($data['jourSemaine'] ?? $menu->getJourSemaine());
        $menu->setPrix($data['prix'] ?? $menu->getPrix());
        $menu->setTag($data['tag'] ?? $menu->getTag());
        $menu->setActif($data['actif'] ?? $menu->getActif());
        
        if (isset($data['date'])) {
            if (!empty($data['date'])) {
                $menu->setDate(new \DateTime($data['date']));
            } else {
                $menu->setDate(null);
            }
        }
        
        // Update menu dishes if provided
        if (isset($data['dishes'])) {
            // Remove existing menu dishes
            foreach ($menu->getMenuPlats() as $menuPlat) {
                $this->entityManager->remove($menuPlat);
            }
            
            // Add new dishes
            foreach ($data['dishes'] as $dishData) {
                // Handle both old format (just IDs) and new format (objects with id and ordre)
                $dishId = is_array($dishData) ? $dishData['id'] : $dishData;
                $ordre = is_array($dishData) ? ($dishData['ordre'] ?? 1) : 1;
                
                $dish = $this->platRepository->find($dishId);
                if ($dish) {
                    $menuPlat = new MenuPlat();
                    $menuPlat->setMenu($menu);
                    $menuPlat->setPlat($dish);
                    $menuPlat->setOrdre($ordre);
                    
                    $this->entityManager->persist($menuPlat);
                }
            }
        }
        
        $errors = $this->validator->validate($menu);
        if (count($errors) > 0) {
            return $this->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => (string) $errors
            ], 400);
        }
        
        $this->entityManager->flush();
        
        return $this->json([
            'success' => true,
            'message' => 'Menu updated successfully',
            'data' => [
                'id' => $menu->getId(),
                'nom' => $menu->getNom(),
                'type' => $menu->getType()
            ]
        ]);
    }

    #[Route('/menus/{id}', name: 'api_admin_menus_delete', methods: ['DELETE'])]
    public function deleteMenu(int $id): JsonResponse
    {
        $menu = $this->menuRepository->find($id);
        if (!$menu) {
            return $this->json(['success' => false, 'message' => 'Menu not found'], 404);
        }
        
        // Check if menu is used in orders
        if ($menu->getCommandeArticles()->count() > 0) {
            return $this->json([
                'success' => false,
                'message' => 'Cannot delete menu that has been ordered.'
            ], 400);
        }
        
        $this->entityManager->remove($menu);
        $this->entityManager->flush();
        
        return $this->json([
            'success' => true,
            'message' => 'Menu deleted successfully'
        ]);
    }

    // ========================
    // STATISTICS
    // ========================

    #[Route('/stats', name: 'api_admin_menu_stats', methods: ['GET'])]
    public function getMenuStats(): JsonResponse
    {
        $totalCategories = $this->categoryRepository->count(['actif' => true]);
        $totalDishes = $this->platRepository->count(['disponible' => true]);
        $totalMenus = $this->menuRepository->count(['actif' => true]);
        $normalMenus = $this->menuRepository->count(['type' => 'normal', 'actif' => true]);
        $dailyMenus = $this->menuRepository->count(['type' => 'menu_du_jour', 'actif' => true]);
        
        // Today's menus by cuisine (for today's count)
        $today = new \DateTime();
        $todayMenus = $this->menuRepository->findBy([
            'type' => 'menu_du_jour',
            'date' => $today,
            'actif' => true
        ]);
        
        // All daily menus by cuisine (for dropdown counts)
        $allDailyMenus = $this->menuRepository->findBy([
            'type' => 'menu_du_jour',
            'actif' => true
        ]);
        
        $cuisineStats = [
            'marocain' => 0,
            'italien' => 0,
            'international' => 0
        ];
        
        // Count all daily menus by cuisine for dropdown
        foreach ($allDailyMenus as $menu) {
            $tag = $menu->getTag();
            if ($tag && isset($cuisineStats[$tag])) {
                $cuisineStats[$tag]++;
            } else {
                // If no tag is set, we can try to infer from the menu name or set a default
                // For now, let's log this case for debugging
                error_log("Menu du jour without tag found: ID=" . $menu->getId() . ", Name=" . $menu->getNom());
            }
        }
        
        // Calculate average price
        $menus = $this->menuRepository->findBy(['actif' => true]);
        $prices = array_map(fn($m) => (float)$m->getPrix(), $menus);
        $avgPrice = count($prices) > 0 ? array_sum($prices) / count($prices) : 0;
        $minPrice = count($prices) > 0 ? min($prices) : 0;
        $maxPrice = count($prices) > 0 ? max($prices) : 0;
        
        return $this->json([
            'success' => true,
            'data' => [
                'totalMenus' => $totalMenus,
                'normalMenus' => $normalMenus,
                'dailyMenus' => $dailyMenus,
                'todayMenus' => count($todayMenus),
                'cuisine' => $cuisineStats,
                'pricing' => [
                    'average' => round($avgPrice, 2),
                    'min' => $minPrice,
                    'max' => $maxPrice
                ],
                'categories' => [
                    'total' => $totalCategories,
                ],
                'dishes' => [
                    'total' => $totalDishes,
                    'available' => $this->platRepository->count(['disponible' => true]),
                    'unavailable' => $this->platRepository->count(['disponible' => false])
                ]
            ]
        ]);
    }
    
    #[Route('/dishes/by-cuisine', name: 'api_admin_dishes_by_cuisine', methods: ['GET'])]
    public function getDishesByCuisine(Request $request): JsonResponse
    {
        $cuisine = $request->query->get('cuisine');
        $course = $request->query->get('course'); // entrée, plat, dessert
        
        $queryBuilder = $this->platRepository->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->where('p.disponible = :disponible')
            ->setParameter('disponible', true);
        
        // Filter by cuisine if provided (using category name matching)
        if ($cuisine && in_array($cuisine, ['marocain', 'italien', 'international'])) {
            $queryBuilder->andWhere('c.nom LIKE :cuisineLike OR p.categorie LIKE :cuisineLikeOld')
                ->setParameter('cuisineLike', '%' . $cuisine . '%')
                ->setParameter('cuisineLikeOld', '%' . $cuisine . '%');
        }
        
        // Filter by course if provided (using category name matching)
        if ($course && in_array($course, ['entrée', 'plat', 'dessert'])) {
            $queryBuilder->andWhere('c.nom LIKE :courseLike OR p.categorie LIKE :courseLikeOld')
                ->setParameter('courseLike', '%' . $course . '%')
                ->setParameter('courseLikeOld', '%' . $course . '%');
        }
        
        $dishes = $queryBuilder->orderBy('p.nom', 'ASC')->getQuery()->getResult();
        
        $data = [];
        foreach ($dishes as $dish) {
            // Extract cuisine and course from category name or use defaults
            $categoryName = $dish->getCategory() ? $dish->getCategory()->getNom() : $dish->getCategorie();
            $extractedCuisine = $this->extractCuisineFromCategory($categoryName);
            $extractedCourse = $this->extractCourseFromCategory($categoryName);
            
            $data[] = [
                'id' => $dish->getId(),
                'nom' => $dish->getNom(),
                'description' => $dish->getDescription(),
                'prix' => $dish->getPrix(),
                'typePlat' => $extractedCourse,
                'typeCuisine' => $extractedCuisine,
                'cuisine' => $extractedCuisine, // For backwards compatibility
                'disponible' => $dish->getDisponible(),
                'category' => $dish->getCategory() ? [
                    'id' => $dish->getCategory()->getId(),
                    'nom' => $dish->getCategory()->getNom()
                ] : [
                    'id' => null,
                    'nom' => $dish->getCategorie()
                ],
                'imageUrl' => $this->storage->resolveUri($dish, 'imageFile'),
                'image' => $this->storage->resolveUri($dish, 'imageFile')
            ];
        }
        
        return $this->json([
            'success' => true,
            'data' => $data,
            'count' => count($data),
            'filters' => [
                'cuisine' => $cuisine,
                'course' => $course
            ]
        ]);
    }
    
    private function extractCuisineFromCategory(?string $categoryName): ?string
    {
        if (!$categoryName) return null;
        
        $categoryLower = strtolower($categoryName);
        
        if (strpos($categoryLower, 'marocain') !== false || strpos($categoryLower, 'maroc') !== false) {
            return 'marocain';
        }
        if (strpos($categoryLower, 'italien') !== false || strpos($categoryLower, 'italie') !== false) {
            return 'italien';
        }
        if (strpos($categoryLower, 'international') !== false || strpos($categoryLower, 'monde') !== false) {
            return 'international';
        }
        
        return null;
    }
    
    private function extractCourseFromCategory(?string $categoryName): ?string
    {
        if (!$categoryName) return null;
        
        $categoryLower = strtolower($categoryName);
        
        if (strpos($categoryLower, 'entrée') !== false || strpos($categoryLower, 'entree') !== false) {
            return 'entrée';
        }
        if (strpos($categoryLower, 'plat') !== false || strpos($categoryLower, 'principal') !== false) {
            return 'plat';
        }
        if (strpos($categoryLower, 'dessert') !== false) {
            return 'dessert';
        }
        
        return null;
    }
} 