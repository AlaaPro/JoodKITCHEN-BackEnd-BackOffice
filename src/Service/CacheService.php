<?php

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use App\Repository\PlatRepository;
use App\Repository\MenuRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Menu;
use App\Entity\Plat;
use App\Enum\OrderStatus;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Psr\Cache\CacheException;

class CacheService
{
    private const CACHE_TTL = 3600; // 1 hour
    private const MENU_CACHE_TTL = 1800; // 30 minutes for menus (more dynamic)
    
    public function __construct(
        private CacheInterface $cache,
        private PlatRepository $platRepository,
        private MenuRepository $menuRepository,
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Get cached available plats with categories
     */
    public function getAvailablePlats(): array
    {
        return $this->cache->get('plats.available', function (ItemInterface $item): array {
            $item->expiresAfter(3600); // 1 hour
            
            $plats = $this->platRepository->findBy(['disponible' => true], ['categorie' => 'ASC', 'nom' => 'ASC']);
            
            $categorizedPlats = [];
            foreach ($plats as $plat) {
                $category = $plat->getCategorie();
                if (!isset($categorizedPlats[$category])) {
                    $categorizedPlats[$category] = [];
                }
                
                $categorizedPlats[$category][] = [
                    'id' => $plat->getId(),
                    'nom' => $plat->getNom(),
                    'description' => $plat->getDescription(),
                    'prix' => $plat->getPrix(),
                    'image' => $plat->getImage(),
                    'allergenes' => $plat->getAllergenes(),
                    'tempsPreparation' => $plat->getTempsPreparation()
                ];
            }
            
            return $categorizedPlats;
        });
    }

    /**
     * Get cached active menus
     */
    public function getActiveMenus(): array
    {
        return $this->cache->get('menus.active', function (ItemInterface $item): array {
            $item->expiresAfter(self::MENU_CACHE_TTL);
            
            $menus = $this->menuRepository->findBy(['actif' => true], ['type' => 'ASC', 'nom' => 'ASC']);
            
            $result = [];
            foreach ($menus as $menu) {
                $menuData = [
                    'id' => $menu->getId(),
                    'nom' => $menu->getNom(),
                    'description' => $menu->getDescription(),
                    'type' => $menu->getType(),
                    'prix' => $menu->getPrix(),
                    'tag' => $menu->getTag(),
                    'date' => $menu->getDate()?->format('Y-m-d'),
                    'jourSemaine' => $menu->getJourSemaine(),
                    'plats' => []
                ];
                
                foreach ($menu->getMenuPlats() as $menuPlat) {
                    $plat = $menuPlat->getPlat();
                    if ($plat->getDisponible()) {
                        $menuData['plats'][] = [
                            'id' => $plat->getId(),
                            'nom' => $plat->getNom(),
                            'description' => $plat->getDescription(),
                            'ordre' => $menuPlat->getOrdre()
                        ];
                    }
                }
                
                $result[] = $menuData;
            }
            
            return $result;
        });
    }

    /**
     * Get menu of the day (cached)
     */
    public function getMenuOfTheDay(?\DateTimeInterface $date = null): ?array
    {
        $date = $date ?? new \DateTime();
        $cacheKey = 'menu.day.' . $date->format('Y-m-d');
        
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($date): ?array {
            $item->expiresAfter(self::MENU_CACHE_TTL);
            
            $menus = $this->menuRepository->findMenuOfTheDay($date);
            
            if (empty($menus)) {
                return null;
            }
            
            // Get the first menu of the day
            $menu = $menus[0];
            
            $menuData = [
                'id' => $menu->getId(),
                'nom' => $menu->getNom(),
                'description' => $menu->getDescription(),
                'prix' => $menu->getPrix(),
                'date' => $menu->getDate()?->format('Y-m-d'),
                'plats' => []
            ];
            
            foreach ($menu->getMenuPlats() as $menuPlat) {
                $plat = $menuPlat->getPlat();
                $menuData['plats'][] = [
                    'id' => $plat->getId(),
                    'nom' => $plat->getNom(),
                    'description' => $plat->getDescription(),
                    'ordre' => $menuPlat->getOrdre()
                ];
            }
            
            return $menuData;
        });
    }

    /**
     * Get user's order history (cached for frequent customers)
     */
    public function getUserOrderHistory(int $userId): array
    {
        $cacheKey = "user.orders.{$userId}";
        
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($userId): array {
            $item->expiresAfter(900); // 15 minutes for order history
            
            $qb = $this->entityManager->createQueryBuilder();
            $orders = $qb->select('c', 'ca', 'p', 'm')
                ->from('App\Entity\Commande', 'c')
                ->leftJoin('c.commandeArticles', 'ca')
                ->leftJoin('ca.plat', 'p')
                ->leftJoin('ca.menu', 'm')
                ->where('c.user = :userId')
                ->setParameter('userId', $userId)
                ->orderBy('c.dateCommande', 'DESC')
                ->setMaxResults(10)
                ->getQuery()
                ->getResult();
            
            return array_map(function($order) {
                return [
                    'id' => $order->getId(),
                    'dateCommande' => $order->getDateCommande()->format('Y-m-d H:i:s'),
                    'statut' => $order->getStatut(),
                    'total' => $order->getTotal(),
                    'itemsCount' => $order->getCommandeArticles()->count()
                ];
            }, $orders);
        });
    }

    /**
     * Clear plats cache
     */
    public function clearPlatsCache(): void
    {
        $this->cache->delete('plats.available');
    }

    public function clearMenusCache(): void
    {
        $this->cache->delete('menus.active');
        
        // Clear daily menu cache for next 7 days
        for ($i = 0; $i < 7; $i++) {
            $date = (new \DateTime())->modify("+{$i} days");
            $this->cache->delete('menu.day.' . $date->format('Y-m-d'));
        }
    }

    public function clearUserOrderCache(int $userId): void
    {
        $this->cache->delete("user.orders.{$userId}");
    }

    /**
     * Get popular plats (cached analytics)
     */
    public function getPopularPlats(int $limit = 10): array
    {
        return $this->cache->get('analytics.popular_plats', function (ItemInterface $item) use ($limit): array {
            $item->expiresAfter(1800); // 30 minutes
            
            $qb = $this->entityManager->createQueryBuilder();
            $result = $qb->select('p.id, p.nom, COUNT(ca.id) as orderCount')
                ->from('App\Entity\CommandeArticle', 'ca')
                ->join('ca.plat', 'p')
                ->join('ca.commande', 'c')
                ->where('c.dateCommande >= :lastMonth')
                ->setParameter('lastMonth', new \DateTime('-1 month'))
                ->groupBy('p.id')
                ->orderBy('orderCount', 'DESC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getArrayResult();
            
            return $result;
        });
    }

    /**
     * Get kitchen statistics (cached)
     */
    public function getKitchenStats(): array
    {
        return $this->cache->get('kitchen.stats', function (ItemInterface $item): array {
            $item->expiresAfter(300); // 5 minutes
            
            $qb = $this->entityManager->createQueryBuilder();
            
            // Orders today
            $ordersToday = $qb->select('COUNT(c.id)')
                ->from('App\Entity\Commande', 'c')
                ->where('DATE(c.dateCommande) = CURRENT_DATE()')
                ->getQuery()
                ->getSingleScalarResult();
            
            // Pending orders
            $qb = $this->entityManager->createQueryBuilder();
            $pendingOrders = $qb->select('COUNT(c.id)')
                ->from('App\Entity\Commande', 'c')
                ->where('c.statut = :status')
                ->setParameter('status', OrderStatus::PENDING->value)
                ->getQuery()
                ->getSingleScalarResult();
            
            // Revenue today
            $qb = $this->entityManager->createQueryBuilder();
            $revenueToday = $qb->select('SUM(c.total)')
                ->from('App\Entity\Commande', 'c')
                ->where('DATE(c.dateCommande) = CURRENT_DATE()')
                ->andWhere('c.statut != :cancelled')
                ->setParameter('cancelled', OrderStatus::CANCELLED->value)
                ->getQuery()
                ->getSingleScalarResult() ?? 0;
            
            return [
                'orders_today' => (int)$ordersToday,
                'pending_orders' => (int)$pendingOrders,
                'revenue_today' => (float)$revenueToday,
                'last_updated' => (new \DateTime())->format('Y-m-d H:i:s')
            ];
        });
    }

    /**
     * Check if a key exists in cache
     */
    public function has(string $key): bool
    {
        return null !== $this->cache->get($key, function() { return null; });
    }

    /**
     * Get a value from cache
     */
    public function get(string $key): mixed
    {
        return $this->cache->get($key, function (ItemInterface $item) {
            return null;
        });
    }

    /**
     * Set a value in cache with TTL
     */
    public function set(string $key, mixed $value, int $ttl = 3600): void
    {
        $this->cache->get($key, function (ItemInterface $item) use ($value, $ttl) {
            $item->expiresAfter($ttl);
            return $value;
        });
    }
} 