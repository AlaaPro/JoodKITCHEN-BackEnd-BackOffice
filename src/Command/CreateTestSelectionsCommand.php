<?php

namespace App\Command;

use App\Entity\Abonnement;
use App\Entity\AbonnementSelection;
use App\Entity\Menu;
use App\Entity\Plat;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-test-selections',
    description: 'Create test AbonnementSelection records for calendar testing'
)]
class CreateTestSelectionsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Get active abonnements
        $abonnements = $this->entityManager->getRepository(Abonnement::class)
            ->findBy(['statut' => 'actif']);

        if (empty($abonnements)) {
            $io->error('No active abonnements found!');
            return Command::FAILURE;
        }

        // Get menus and plats
        $menus = $this->entityManager->getRepository(Menu::class)->findAll();
        $plats = $this->entityManager->getRepository(Plat::class)->findAll();

        $io->info(sprintf('Found %d active abonnements, %d menus, %d plats', 
            count($abonnements), count($menus), count($plats)));

        $cuisineTypes = ['marocain', 'italien', 'international'];
        $statuses = ['selectionne', 'confirme', 'prepare', 'livre'];
        $selectionsCreated = 0;

        foreach ($abonnements as $abonnement) {
            $io->info(sprintf('Creating selections for abonnement #%d', $abonnement->getId()));

            // Create selections for the entire subscription period
            $startDate = $abonnement->getDateDebut();
            $endDate = min($abonnement->getDateFin(), new \DateTime('+1 week')); // Don't go too far into future

            for ($date = clone $startDate; $date <= $endDate; $date->modify('+1 day')) {
                // BUSINESS RULE: Only weekdays (Monday=1 to Friday=5), NO weekends
                if (!in_array($date->format('N'), ['1', '2', '3', '4', '5'])) {
                    continue; // Skip weekends completely
                }
                
                // BUSINESS RULE: Always create 1 meal per weekday (no randomness for skipping)
                $selectionsCount = 1; // Always 1 meal per weekday
                
                for ($i = 0; $i < $selectionsCount; $i++) {
                    $selection = new AbonnementSelection();
                    $selection->setAbonnement($abonnement);
                    $selection->setDateRepas(clone $date);
                    $selection->setCuisineType($cuisineTypes[array_rand($cuisineTypes)]);
                    $selection->setJourSemaine($this->getFrenchDayName($date));
                    
                    // Assign menu, plat, or default
                    if (!empty($menus) && rand(0, 1)) {
                        $selection->setMenu($menus[array_rand($menus)]);
                        $selection->setTypeSelection('menu_du_jour');
                        $selection->setPrix('15.00');
                    } elseif (!empty($plats)) {
                        $selection->setPlat($plats[array_rand($plats)]);
                        $selection->setTypeSelection('plat_individuel');
                        $selection->setPrix('12.00');
                    } else {
                        $selection->setTypeSelection('menu_normal');
                        $selection->setPrix('10.00');
                    }
                    
                    // Set realistic status based on date
                    $now = new \DateTime();
                    $daysDiff = $now->diff($date)->days;
                    $isPast = $date < $now;
                    $isFuture = $date > $now;
                    
                    if ($isPast) {
                        // Past dates: mostly delivered, some prepared
                        if ($daysDiff > 3) {
                            $selection->setStatut('livre'); // Old dates = delivered
                        } else {
                            $selection->setStatut(rand(0, 100) < 80 ? 'livre' : 'prepare');
                        }
                    } elseif ($isFuture) {
                        // Future dates: mostly selected, some confirmed
                        $selection->setStatut(rand(0, 100) < 70 ? 'selectionne' : 'confirme');
                    } else {
                        // Today: in preparation or confirmed
                        $selection->setStatut(rand(0, 100) < 60 ? 'prepare' : 'confirme');
                    }
                    
                    $this->entityManager->persist($selection);
                    $selectionsCreated++;
                }
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf('Created %d test AbonnementSelection records!', $selectionsCreated));

        return Command::SUCCESS;
    }

    private function getFrenchDayName(\DateTime $date): string
    {
        $dayMapping = [
            1 => 'lundi',
            2 => 'mardi', 
            3 => 'mercredi',
            4 => 'jeudi',
            5 => 'vendredi',
            6 => 'samedi',
            7 => 'dimanche'
        ];
        
        $dayNumber = (int) $date->format('N');
        return $dayMapping[$dayNumber] ?? 'lundi';
    }
} 