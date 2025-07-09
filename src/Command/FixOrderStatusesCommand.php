<?php

namespace App\Command;

use App\Entity\Commande;
use App\Enum\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fix-order-statuses',
    description: 'Fix order statuses in database to match OrderStatus enum values'
)]
class FixOrderStatusesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Fixing Order Statuses');
        $io->info('Checking for orders with invalid statuses...');

        // Map old status values to new OrderStatus enum values
        $statusMap = [
            'confirmee' => OrderStatus::CONFIRMED->value,  // Fix the old 'confirmee' to 'confirme'
            'en_attente' => OrderStatus::PENDING->value,
            'confirme' => OrderStatus::CONFIRMED->value,
            'en_preparation' => OrderStatus::PREPARING->value,
            'pret' => OrderStatus::READY->value,
            'en_livraison' => OrderStatus::DELIVERING->value,
            'livre' => OrderStatus::DELIVERED->value,
            'annule' => OrderStatus::CANCELLED->value,
        ];

        // Get all valid status values from enum
        $validStatuses = OrderStatus::getAll();
        
        // Find orders with invalid statuses
        $commandeRepository = $this->entityManager->getRepository(Commande::class);
        $allOrders = $commandeRepository->findAll();
        
        $fixedCount = 0;
        $totalCount = count($allOrders);
        
        $io->progressStart($totalCount);
        
        foreach ($allOrders as $order) {
            $currentStatus = $order->getStatut();
            
            // Check if status needs fixing
            if (!in_array($currentStatus, $validStatuses)) {
                if (isset($statusMap[$currentStatus])) {
                    $newStatus = $statusMap[$currentStatus];
                    $order->setStatut($newStatus);
                    $fixedCount++;
                    
                    $io->text("Fixed order #{$order->getId()}: '{$currentStatus}' -> '{$newStatus}'");
                } else {
                    $io->warning("Unknown status '{$currentStatus}' for order #{$order->getId()}. Setting to 'en_attente'.");
                    $order->setStatut(OrderStatus::PENDING->value);
                    $fixedCount++;
                }
            }
            
            $io->progressAdvance();
        }
        
        $io->progressFinish();
        
        if ($fixedCount > 0) {
            $this->entityManager->flush();
            $io->success("Fixed {$fixedCount} order(s) with invalid statuses.");
        } else {
            $io->success('All order statuses are already valid.');
        }
        
        // Show summary of current statuses
        $io->section('Current Status Distribution');
        $statusDistribution = [];
        foreach ($allOrders as $order) {
            $status = $order->getStatut();
            $statusDistribution[$status] = ($statusDistribution[$status] ?? 0) + 1;
        }
        
        $tableRows = [];
        foreach ($statusDistribution as $status => $count) {
            // Get status label if it's a valid enum value
            $label = $status;
            foreach (OrderStatus::cases() as $enumCase) {
                if ($enumCase->value === $status) {
                    $label = $enumCase->getLabel();
                    break;
                }
            }
            $tableRows[] = [$status, $label, $count];
        }
        
        $io->table(['Status Value', 'Label', 'Count'], $tableRows);
        
        return Command::SUCCESS;
    }
} 