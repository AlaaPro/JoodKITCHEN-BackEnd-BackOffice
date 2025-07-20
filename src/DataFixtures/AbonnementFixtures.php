<?php

namespace App\DataFixtures;

use App\Entity\Abonnement;
use App\Entity\AbonnementSelection;
use App\Entity\User;
use App\Entity\ClientProfile;
use App\Entity\Menu;
use App\Entity\Plat;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AbonnementFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function getDependencies(): array
    {
        return [
            AppFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        // Flush any pending entities from AppFixtures first
        $manager->flush();
        
        // Create additional demo clients first
        $demoClients = $this->createDemoClients($manager);
        
        // Get the existing client from AppFixtures
        $existingClient = $manager->getRepository(User::class)->findOneBy(['email' => 'client@joodkitchen.com']);
        if ($existingClient) {
            $demoClients[] = $existingClient;
        }

        // Create demo abonnements with various scenarios
        $this->createDemoAbonnements($manager, $demoClients);

        $manager->flush();
    }

    private function createDemoClients(ObjectManager $manager): array
    {
        $clients = [];
        
        $clientsData = [
            [
                'email' => 'marie.dupont@gmail.com',
                'nom' => 'Dupont',
                'prenom' => 'Marie',
                'telephone' => '0612345678',
                'ville' => 'Casablanca',
                'adresse' => '15 Rue Mohamed V, Casablanca',
                'genre' => 'F',
                'dateNaissance' => '1985-03-20',
                'adresseLivraison' => '15 Rue Mohamed V, Casablanca',
                'pointsFidelite' => 250
            ],
            [
                'email' => 'ahmed.benali@outlook.com',
                'nom' => 'Ben Ali',
                'prenom' => 'Ahmed',
                'telephone' => '0698765432',
                'ville' => 'Rabat',
                'adresse' => '22 Avenue Hassan II, Rabat',
                'genre' => 'M',
                'dateNaissance' => '1979-11-08',
                'adresseLivraison' => '22 Avenue Hassan II, Rabat',
                'pointsFidelite' => 180
            ],
            [
                'email' => 'sarah.martin@yahoo.fr',
                'nom' => 'Martin',
                'prenom' => 'Sarah',
                'telephone' => '0634567890',
                'ville' => 'Casablanca',
                'adresse' => '8 Boulevard Zerktouni, Casablanca',
                'genre' => 'F',
                'dateNaissance' => '1992-07-14',
                'adresseLivraison' => '8 Boulevard Zerktouni, Casablanca',
                'pointsFidelite' => 320
            ],
            [
                'email' => 'khalid.alami@gmail.com',
                'nom' => 'Alami',
                'prenom' => 'Khalid',
                'telephone' => '0656789012',
                'ville' => 'Fès',
                'adresse' => '12 Rue Talaa Kebira, Fès',
                'genre' => 'M',
                'dateNaissance' => '1988-01-25',
                'adresseLivraison' => '12 Rue Talaa Kebira, Fès',
                'pointsFidelite' => 95
            ],
            [
                'email' => 'aicha.zahra@hotmail.com',
                'nom' => 'Zahra',
                'prenom' => 'Aicha',
                'telephone' => '0678901234',
                'ville' => 'Marrakech',
                'adresse' => '25 Avenue Mohammed VI, Marrakech',
                'genre' => 'F',
                'dateNaissance' => '1983-09-12',
                'adresseLivraison' => '25 Avenue Mohammed VI, Marrakech',
                'pointsFidelite' => 400
            ],
            [
                'email' => 'youssef.idrissi@gmail.com',
                'nom' => 'Idrissi',
                'prenom' => 'Youssef',
                'telephone' => '0623456789',
                'ville' => 'Casablanca',
                'adresse' => '33 Rue de la Liberté, Casablanca',
                'genre' => 'M',
                'dateNaissance' => '1975-04-30',
                'adresseLivraison' => '33 Rue de la Liberté, Casablanca',
                'pointsFidelite' => 150
            ]
        ];

        foreach ($clientsData as $data) {
            // Check if user already exists
            $existingUser = $manager->getRepository(User::class)->findOneBy(['email' => $data['email']]);
            
            if ($existingUser) {
                $clients[] = $existingUser;
                continue; // Skip creating if user already exists
            }

            $client = new User();
            $client->setEmail($data['email']);
            $client->setNom($data['nom']);
            $client->setPrenom($data['prenom']);
            $client->setTelephone($data['telephone']);
            $client->setVille($data['ville']);
            $client->setAdresse($data['adresse']);
            $client->setGenre($data['genre']);
            $client->setDateNaissance(new \DateTime($data['dateNaissance']));
            $client->setPassword($this->passwordHasher->hashPassword($client, 'demo123'));
            $client->setRoles(['ROLE_CLIENT']);
            $manager->persist($client);

            $clientProfile = new ClientProfile();
            $clientProfile->setUser($client);
            $clientProfile->setAdresseLivraison($data['adresseLivraison']);
            $clientProfile->setPointsFidelite($data['pointsFidelite']);
            $manager->persist($clientProfile);

            $clients[] = $client;
        }

        return $clients;
    }

    private function createDemoAbonnements(ObjectManager $manager, array $clients): void
    {
        // Define abonnement scenarios with realistic data
        $abonnementsData = [
            // Active subscriptions
            [
                'client' => 0, // Marie Dupont
                'type' => 'hebdo',
                'repasParJour' => 2,
                'statut' => 'actif',
                'dateDebut' => '-2 weeks',
                'dateFin' => '+4 weeks',
                'createdAt' => '-3 weeks'
            ],
            [
                'client' => 1, // Ahmed Ben Ali
                'type' => 'mensuel',
                'repasParJour' => 1,
                'statut' => 'actif',
                'dateDebut' => '-1 month',
                'dateFin' => '+1 month',
                'createdAt' => '-5 weeks'
            ],
            [
                'client' => 2, // Sarah Martin
                'type' => 'hebdo',
                'repasParJour' => 3,
                'statut' => 'actif',
                'dateDebut' => '-1 week',
                'dateFin' => '+5 weeks',
                'createdAt' => '-2 weeks'
            ],

            // Pending confirmations (en_confirmation)
            [
                'client' => 3, // Khalid Alami
                'type' => 'hebdo',
                'repasParJour' => 2,
                'statut' => 'en_confirmation',
                'dateDebut' => '+1 week',
                'dateFin' => '+7 weeks',
                'createdAt' => '-2 days'
            ],
            [
                'client' => 4, // Aicha Zahra
                'type' => 'mensuel',
                'repasParJour' => 1,
                'statut' => 'en_confirmation',
                'dateDebut' => '+3 days',
                'dateFin' => '+1 month 3 days',
                'createdAt' => '-1 day'
            ],

            // Suspended subscriptions
            [
                'client' => 5, // Youssef Idrissi
                'type' => 'hebdo',
                'repasParJour' => 2,
                'statut' => 'suspendu',
                'dateDebut' => '-3 weeks',
                'dateFin' => '+3 weeks',
                'createdAt' => '-4 weeks'
            ],

            // Expired subscriptions
            [
                'client' => 0, // Marie Dupont (previous subscription)
                'type' => 'hebdo',
                'repasParJour' => 1,
                'statut' => 'expire',
                'dateDebut' => '-8 weeks',
                'dateFin' => '-2 weeks',
                'createdAt' => '-10 weeks'
            ],
            [
                'client' => 1, // Ahmed Ben Ali (previous subscription)
                'type' => 'mensuel',
                'repasParJour' => 2,
                'statut' => 'expire',
                'dateDebut' => '-3 months',
                'dateFin' => '-1 month',
                'createdAt' => '-4 months'
            ],

            // Cancelled subscriptions
            [
                'client' => 2, // Sarah Martin (cancelled)
                'type' => 'hebdo',
                'repasParJour' => 2,
                'statut' => 'annule',
                'dateDebut' => '-6 weeks',
                'dateFin' => '+2 weeks',
                'createdAt' => '-7 weeks'
            ],

            // More realistic mixed scenarios
            [
                'client' => 3, // Khalid Alami (active)
                'type' => 'mensuel',
                'repasParJour' => 3,
                'statut' => 'actif',
                'dateDebut' => '-2 weeks',
                'dateFin' => '+6 weeks',
                'createdAt' => '-3 weeks'
            ],
            [
                'client' => 4, // Aicha Zahra (will expire soon)
                'type' => 'hebdo',
                'repasParJour' => 2,
                'statut' => 'actif',
                'dateDebut' => '-5 weeks',
                'dateFin' => '+3 days',
                'createdAt' => '-6 weeks'
            ],
            [
                'client' => 5, // Youssef Idrissi (recent active)
                'type' => 'hebdo',
                'repasParJour' => 1,
                'statut' => 'actif',
                'dateDebut' => '-3 days',
                'dateFin' => '+4 weeks 4 days',
                'createdAt' => '-1 week'
            ]
        ];

        // If we have the original client from AppFixtures, add subscriptions for them too
        if (count($clients) > 6) {
            $abonnementsData[] = [
                'client' => 6, // Original client from AppFixtures
                'type' => 'mensuel',
                'repasParJour' => 2,
                'statut' => 'actif',
                'dateDebut' => '-1 week',
                'dateFin' => '+3 weeks',
                'createdAt' => '-2 weeks'
            ];
        }

        foreach ($abonnementsData as $data) {
            if (!isset($clients[$data['client']])) {
                continue; // Skip if client doesn't exist
            }

            $abonnement = new Abonnement();
            $abonnement->setUser($clients[$data['client']]);
            $abonnement->setType($data['type']);
            $abonnement->setRepasParJour($data['repasParJour']);
            $abonnement->setStatut($data['statut']);
            $abonnement->setDateDebut(new \DateTime($data['dateDebut']));
            $abonnement->setDateFin(new \DateTime($data['dateFin']));
            
            // Manually set creation date to simulate realistic timeline
            $createdAt = new \DateTime($data['createdAt']);
            $reflection = new \ReflectionClass($abonnement);
            $property = $reflection->getProperty('createdAt');
            $property->setAccessible(true);
            $property->setValue($abonnement, $createdAt);
            
            $property = $reflection->getProperty('updatedAt');
            $property->setAccessible(true);
            $property->setValue($abonnement, $createdAt);

            $manager->persist($abonnement);

            // Add some meal selections for active subscriptions
            if (in_array($data['statut'], ['actif', 'suspendu'])) {
                $this->createMealSelections($manager, $abonnement);
            }
        }
    }

    private function createMealSelections(ObjectManager $manager, Abonnement $abonnement): void
    {
        // Flush to ensure we can query for Menu/Plat entities
        $manager->flush();
        
        // Get some existing menus/plats if they exist
        $menus = $manager->getRepository(Menu::class)->findAll();
        $plats = $manager->getRepository(Plat::class)->findAll();
        
        // Create selections even if no specific menus/plats exist
        // We'll create basic selections with default data
        
        // Create selections within the subscription's actual date range
        $subscriptionStart = $abonnement->getDateDebut();
        $subscriptionEnd = $abonnement->getDateFin();
        $now = new \DateTime();
        
        // Create selections for a reasonable range within the subscription
        // For demo purposes, create selections from subscription start to a reasonable future date
        $startDate = $subscriptionStart;
        $threeWeeksLater = new \DateTime($subscriptionStart->format('Y-m-d'));
        $threeWeeksLater->modify('+3 weeks');
        $endDate = min($subscriptionEnd, $threeWeeksLater);
        
        // Ensure we have at least a week of data from subscription start
        $oneWeekLater = new \DateTime($subscriptionStart->format('Y-m-d'));
        $oneWeekLater->modify('+1 week');
        $endDate = max($endDate, $oneWeekLater);
        
        // Skip if date range doesn't make sense
        if ($startDate > $endDate) {
            return;
        }
        
        $cuisineTypes = ['marocain', 'italien', 'international'];

        for ($date = clone $startDate; $date <= $endDate; $date->modify('+1 day')) {
            // Skip weekends for variety
            if (in_array($date->format('N'), ['6', '7']) && rand(0, 100) < 60) {
                continue;
            }
            
            // Add randomness - not every subscription has selections every day
            if (rand(0, 100) < 30) {
                continue; // Skip this day randomly
            }

            // Create 1-2 selections per day based on repasParJour, with some randomness
            $maxSelections = min($abonnement->getRepasParJour(), 2);
            $selectionsCount = rand(1, $maxSelections);
            
            for ($i = 0; $i < $selectionsCount; $i++) {
                $selection = new AbonnementSelection();
                $selection->setAbonnement($abonnement);
                $selection->setDateRepas(clone $date);
                $selection->setCuisineType($cuisineTypes[array_rand($cuisineTypes)]);
                $selection->setJourSemaine($this->getFrenchDayName($date));
                
                // Assign a random menu or plat if available, otherwise create with defaults
                if (!empty($menus)) {
                    $selection->setMenu($menus[array_rand($menus)]);
                    $selection->setTypeSelection('menu_du_jour');
                    $selection->setPrix('15.00');
                } elseif (!empty($plats)) {
                    $selection->setPlat($plats[array_rand($plats)]);
                    $selection->setTypeSelection('plat_individuel');
                    $selection->setPrix('12.00');
                } else {
                    // Create selection even without specific menu/plat
                    $selection->setTypeSelection('menu_normal');
                    $selection->setPrix('10.00');
                    $selection->setNotes('Menu standard - ' . $selection->getCuisineType());
                }
                
                // Set realistic status distribution
                $statuses = ['selectionne', 'confirme', 'prepare', 'livre'];
                $weights = [20, 50, 20, 10]; // Weighted distribution
                $randomValue = rand(0, 99);
                $statusIndex = 0;
                $cumulative = 0;
                foreach ($weights as $index => $weight) {
                    $cumulative += $weight;
                    if ($randomValue < $cumulative) {
                        $statusIndex = $index;
                        break;
                    }
                }
                $selection->setStatut($statuses[$statusIndex]);
                
                $manager->persist($selection);
            }
        }
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