<?php

namespace App\Repository;

use App\Entity\OrderStatusHistory;
use App\Entity\Commande;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderStatusHistory>
 */
class OrderStatusHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderStatusHistory::class);
    }

    /**
     * Get status history for a specific order
     */
    public function findByOrder(Commande $commande): array
    {
        return $this->createQueryBuilder('osh')
            ->andWhere('osh.commande = :commande')
            ->setParameter('commande', $commande)
            ->orderBy('osh.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get the latest status change for a specific order and status
     */
    public function findLatestStatusChange(Commande $commande, string $status): ?OrderStatusHistory
    {
        return $this->createQueryBuilder('osh')
            ->andWhere('osh.commande = :commande')
            ->andWhere('osh.status = :status')
            ->setParameter('commande', $commande)
            ->setParameter('status', $status)
            ->orderBy('osh.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get the timestamp when an order reached a specific status
     */
    public function getStatusTimestamp(Commande $commande, string $status): ?\DateTimeInterface
    {
        $statusHistory = $this->findLatestStatusChange($commande, $status);
        return $statusHistory ? $statusHistory->getCreatedAt() : null;
    }

    /**
     * Get status history for multiple orders (for kitchen dashboard)
     */
    public function findStatusHistoryForOrders(array $orders): array
    {
        if (empty($orders)) {
            return [];
        }

        $orderIds = array_map(fn($order) => $order->getId(), $orders);
        
        return $this->createQueryBuilder('osh')
            ->andWhere('osh.commande IN (:orders)')
            ->setParameter('orders', $orderIds)
            ->orderBy('osh.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get the duration an order spent in a specific status
     */
    public function getStatusDuration(Commande $commande, string $status): ?int
    {
        $statusHistory = $this->findByOrder($commande);
        $statusStart = null;
        $statusEnd = null;

        foreach ($statusHistory as $history) {
            if ($history->getStatus() === $status && !$statusStart) {
                $statusStart = $history->getCreatedAt();
            } elseif ($statusStart && $history->getStatus() !== $status) {
                $statusEnd = $history->getCreatedAt();
                break;
            }
        }

        if (!$statusStart) {
            return null;
        }

        $endTime = $statusEnd ?: new \DateTime();
        return $endTime->getTimestamp() - $statusStart->getTimestamp();
    }
} 