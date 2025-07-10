<?php

namespace App\Command;

use App\Service\OrderHistoryService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:backfill-order-history',
    description: 'Backfill order history snapshots for existing orders to preserve deleted item information'
)]
class BackfillOrderHistoryCommand extends Command
{
    public function __construct(
        private OrderHistoryService $orderHistoryService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be done without making changes')
            ->addOption('stats', null, InputOption::VALUE_NONE, 'Show current order history statistics')
            ->setHelp('
This command creates snapshot data for existing order articles that don\'t have it yet.
This preserves the original item names and descriptions even if the menu items are later deleted.

Examples:
  # Show current statistics
  php bin/console app:backfill-order-history --stats
  
  # Preview what would be done
  php bin/console app:backfill-order-history --dry-run
  
  # Actually perform the backfill
  php bin/console app:backfill-order-history
            ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        // Show statistics if requested
        if ($input->getOption('stats')) {
            $this->showStatistics($io);
            return Command::SUCCESS;
        }

        $isDryRun = $input->getOption('dry-run');
        
        if ($isDryRun) {
            $io->note('DRY RUN MODE - No changes will be made');
        }

        $io->title('Order History Backfill Tool');
        $io->text('This tool creates snapshots of menu items for existing orders to preserve history.');
        
        // Show current stats
        $this->showStatistics($io);
        
        if (!$isDryRun) {
            $io->section('Performing Backfill');
            
            if (!$io->confirm('Do you want to proceed with the backfill operation?', false)) {
                $io->warning('Operation cancelled by user');
                return Command::SUCCESS;
            }
            
            $io->text('Creating snapshots for existing order articles...');
            $count = $this->orderHistoryService->backfillSnapshots();
            
            $io->success(sprintf('Successfully created snapshots for %d order articles', $count));
            
            // Show updated stats
            $io->section('Updated Statistics');
            $this->showStatistics($io);
        } else {
            $io->note('Run without --dry-run to actually perform the backfill operation');
        }

        return Command::SUCCESS;
    }

    private function showStatistics(SymfonyStyle $io): void
    {
        $stats = $this->orderHistoryService->getHistoryStats();
        
        $io->section('Order History Statistics');
        
        $io->table(
            ['Metric', 'Count', 'Percentage'],
            [
                ['Total Order Articles', number_format($stats['totalArticles']), '100%'],
                ['With Snapshots', number_format($stats['articlesWithSnapshots']), $stats['snapshotCoverage'] . '%'],
                ['Deleted Items', number_format($stats['deletedItems']), ''],
                ['Orphaned (Lost Data)', number_format($stats['orphanedArticles']), ''],
            ]
        );
        
        if ($stats['orphanedArticles'] > 0) {
            $io->warning(sprintf(
                '%d order articles have lost their original item data (no snapshot and menu item deleted)',
                $stats['orphanedArticles']
            ));
        }
        
        if ($stats['snapshotCoverage'] < 100) {
            $io->note(sprintf(
                'Coverage: %.1f%% - Run backfill to improve history preservation',
                $stats['snapshotCoverage']
            ));
        } else {
            $io->success('Perfect coverage! All order articles have preserved history.');
        }
    }
} 