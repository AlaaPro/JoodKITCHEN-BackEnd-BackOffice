<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\AdminProfile;
use App\Entity\ClientProfile;
use App\Entity\Commande;
use App\Entity\Menu;
use App\Entity\Plat;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-test-audit-logs',
    description: 'Generate test audit log entries for testing the logs system',
)]
class GenerateTestAuditLogsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Génération des logs d\'audit de test');

        try {
            // Get some existing entities to modify (this will generate audit logs)
            $users = $this->entityManager->getRepository(User::class)->findBy([], [], 5);
            
            if (empty($users)) {
                $io->warning('Aucun utilisateur trouvé. Création d\'utilisateurs de test...');
                $this->createTestUsers();
                $users = $this->entityManager->getRepository(User::class)->findBy([], [], 5);
            }

            $operations = [
                'create_admin' => 'Création d\'un nouvel administrateur',
                'update_user' => 'Modification d\'informations utilisateur',  
                'create_menu' => 'Création d\'un nouveau menu',
                'update_dish' => 'Modification d\'un plat',
                'delete_item' => 'Suppression d\'un élément',
                'create_error_scenarios' => 'Génération de scénarios d\'erreur',
                'security_change' => 'Modification des paramètres de sécurité'
            ];

            $logCount = 0;

            // Generate different types of operations
            foreach ($operations as $operation => $description) {
                $io->writeln("📝 $description...");
                
                switch ($operation) {
                    case 'create_admin':
                        $this->createTestAdmin();
                        break;
                    case 'update_user':
                        $this->updateTestUser($users[array_rand($users)]);
                        break;
                    case 'create_menu':
                        $this->createTestMenu();
                        break;
                    case 'update_dish':
                        $this->updateTestDish();
                        break;
                    case 'delete_item':
                        $this->deleteTestItem();
                        break;
                    case 'create_error_scenarios':
                        $this->generateErrorScenarios();
                        break;
                    case 'security_change':
                        $this->updateSecuritySettings($users[array_rand($users)]);
                        break;
                }
                
                $logCount++;
                usleep(100000); // Small delay between operations
            }

            $io->success("✅ $logCount opérations générées avec succès!");
            $io->note('Les logs d\'audit ont été automatiquement créés par DataDogAuditBundle');
            $io->note('Vous pouvez maintenant tester le système de logs dynamique');

        } catch (\Exception $e) {
            $io->error('Erreur lors de la génération des logs: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function createTestUsers(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $user = new User();
            $user->setEmail("test$i@joodkitchen.ma");
            $user->setNom("Test$i");
            $user->setPrenom("User");
            $user->setTelephone("0612345" . str_pad($i, 3, '0', STR_PAD_LEFT)); // Fixed phone format
            $user->setPassword('$2y$13$test.password.hash'); // Dummy hash
            $user->setRoles(['ROLE_USER']);
            $user->setIsActive(true);

            $this->entityManager->persist($user);
        }
        
        $this->entityManager->flush();
    }

    private function createTestAdmin(): void
    {
        $user = new User();
        $user->setEmail('admin.test' . time() . '@joodkitchen.ma');
        $user->setNom('AdminTest');
        $user->setPrenom('Generated');
        $user->setTelephone('0612' . str_pad(time() % 1000000, 6, '0', STR_PAD_LEFT)); // Properly formatted phone number
        $user->setPassword('$2y$13$test.admin.password.hash');
        $user->setRoles(['ROLE_ADMIN']);
        $user->setIsActive(true);

        $adminProfile = new AdminProfile();
        $adminProfile->setUser($user);
        $adminProfile->setPermissionsAvancees(['view_logs', 'manage_admins']);

        $this->entityManager->persist($user);
        $this->entityManager->persist($adminProfile);
        $this->entityManager->flush();
    }

    private function updateTestUser(User $user): void
    {
        $user->setPrenom($user->getPrenom() . ' Updated');
        $user->setVille('Casablanca Test');
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    private function createTestMenu(): void
    {
        $menu = new Menu();
        $menu->setNom('Menu Test ' . date('H:i:s'));
        $menu->setDescription('Menu généré automatiquement pour test');
        $menu->setDate(new \DateTime());
        $menu->setActif(false); // Set as inactive for testing

        $this->entityManager->persist($menu);
        $this->entityManager->flush();
    }

    private function updateTestDish(): void
    {
        $dishes = $this->entityManager->getRepository(Plat::class)->findBy([], [], 1);
        if (!empty($dishes)) {
            $dish = $dishes[0];
            $dish->setDescription($dish->getDescription() . ' [Modifié ' . date('H:i') . ']');
            
            $this->entityManager->persist($dish);
            $this->entityManager->flush();
        }
    }

    private function deleteTestItem(): void
    {
        // Find a test menu to delete
        $testMenus = $this->entityManager->getRepository(Menu::class)
            ->createQueryBuilder('m')
            ->where('m.nom LIKE :pattern')
            ->setParameter('pattern', 'Menu Test%')
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();

        if (!empty($testMenus)) {
            $this->entityManager->remove($testMenus[0]);
            $this->entityManager->flush();
        }
    }

    private function generateErrorScenarios(): void
    {
        // Scenario 1: Create and delete items (generates warning/error level logs)
        $testMenu = new Menu();
        $testMenu->setNom('Menu à Supprimer ' . date('H:i:s'));
        $testMenu->setDescription('Menu créé pour être supprimé');
        $testMenu->setDate(new \DateTime());
        $testMenu->setActif(false);

        $this->entityManager->persist($testMenu);
        $this->entityManager->flush();

        // Wait a moment then delete it
        usleep(50000);
        $this->entityManager->remove($testMenu);
        $this->entityManager->flush();

        // Scenario 2: Modify and then revert changes (multiple operations)
        $users = $this->entityManager->getRepository(User::class)->findBy([], [], 2);
        foreach ($users as $user) {
            $originalName = $user->getPrenom();
            
            // First modification
            $user->setPrenom($originalName . ' [MODIFIÉ]');
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            
            usleep(10000); // Small delay
            
            // Revert modification (appears as another change)
            $user->setPrenom($originalName);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }

        // Scenario 3: Role changes (security-related operations)
        if (!empty($users)) {
            $user = $users[0];
            $currentRoles = $user->getRoles();
            
            // Add a role
            if (!in_array('ROLE_KITCHEN', $currentRoles)) {
                $user->setRoles(array_merge($currentRoles, ['ROLE_KITCHEN']));
                $this->entityManager->persist($user);
                $this->entityManager->flush();
                
                usleep(10000);
                
                // Remove the role (security change)
                $user->setRoles($currentRoles);
                $this->entityManager->persist($user);
                $this->entityManager->flush();
            }
        }
    }

    private function simulateLoginAttempt(User $user): void
    {
        // Update last login to simulate login activity
        $user->setLastConnexion(new \DateTime());
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    private function updateSecuritySettings(User $user): void
    {
        // Modify user roles or other security-related fields
        $currentRoles = $user->getRoles();
        if (in_array('ROLE_USER', $currentRoles) && !in_array('ROLE_ADMIN', $currentRoles)) {
            $currentRoles[] = 'ROLE_KITCHEN';
            $user->setRoles($currentRoles);
        }
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
} 