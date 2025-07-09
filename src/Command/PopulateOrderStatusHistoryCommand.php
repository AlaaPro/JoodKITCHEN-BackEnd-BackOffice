<?php

namespace App\Command;

use App\Entity\OrderStatusHistory;
use App\Repository\CommandeRepository;
use App\Enum\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:populate-order-status-history',
    description: 'Populate OrderStatusHistory table with realistic timestamps for existing orders',
)]
class PopulateOrderStatusHistoryCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CommandeRepository $commandeRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Populating Order Status History with Realistic Timestamps');
        
        // Get all existing orders
        $orders = $this->commandeRepository->findAll();
        
        if (empty($orders)) {
            $io->success('No orders found to process.');
            return Command::SUCCESS;
        }
        
        $io->progressStart(count($orders));
        $processed = 0;
        
        foreach ($orders as $order) {
            // Check if this order already has status history
            $existingHistory = $this->entityManager->getRepository(OrderStatusHistory::class)
                ->findOneBy(['commande' => $order]);
            
            if ($existingHistory) {
                $io->progressAdvance();
                continue; // Skip if already has history
            }
            
            // Create realistic timestamp based on current order status
            $timestamp = $this->getRealisticTimestamp($order->getStatut());
            
            // Create initial status history record
            $statusHistory = new OrderStatusHistory();
            $statusHistory->setCommande($order);
            $statusHistory->setStatus($order->getStatut());
            $statusHistory->setPreviousStatus(null); // First status has no previous
            $statusHistory->setChangedBy(null); // System generated
            $statusHistory->setComment('Initial status - migrated with realistic timestamp');
            
            // Use realistic timestamp instead of old order date
            $statusHistory->setCreatedAt($timestamp);
            
            $this->entityManager->persist($statusHistory);
            $processed++;
            
            // Flush every 100 records to avoid memory issues
            if ($processed % 100 === 0) {
                $this->entityManager->flush();
            }
            
            $io->progressAdvance();
        }
        
        // Final flush
        $this->entityManager->flush();
        $io->progressFinish();
        
        $io->success(sprintf(
            'Successfully processed %d orders. Created %d status history records with realistic timestamps.',
            count($orders),
            $processed
        ));
        
        return Command::SUCCESS;
    }
    
    /**
     * Generate realistic timestamps based on order status for kitchen workflow
     */
    private function getRealisticTimestamp(string $status): \DateTime
    {
        $now = new \DateTime();
        
        switch ($status) {
            case OrderStatus::PENDING->value:
            case OrderStatus::CONFIRMED->value:
                // New orders: 1-10 minutes ago
                $minutesAgo = rand(1, 10);
                return (clone $now)->modify("-{$minutesAgo} minutes");
                
            case OrderStatus::PREPARING->value:
                // Orders in preparation: 5-45 minutes ago (realistic cooking time)
                $minutesAgo = rand(5, 45);
                return (clone $now)->modify("-{$minutesAgo} minutes");
                
            case OrderStatus::READY->value:
                // Ready orders: 2-30 minutes ago (waiting for pickup/delivery)
                $minutesAgo = rand(2, 30);
                return (clone $now)->modify("-{$minutesAgo} minutes");
                
            case OrderStatus::DELIVERING->value:
                // Out for delivery: 5-60 minutes ago
                $minutesAgo = rand(5, 60);
                return (clone $now)->modify("-{$minutesAgo} minutes");
                
            default:
                // For any other status, use recent timestamp
                $minutesAgo = rand(1, 15);
                return (clone $now)->modify("-{$minutesAgo} minutes");
        }
    }
} 