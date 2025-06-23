<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\ClientProfile;
use App\Entity\KitchenProfile;
use App\Entity\AdminProfile;
use App\Entity\Menu;
use App\Entity\Plat;
use App\Entity\MenuPlat;
use App\Entity\Commande;
use App\Entity\CommandeArticle;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        // Create Admin User
        $admin = new User();
        $admin->setEmail('admin@joodkitchen.com');
        $admin->setNom('Admin');
        $admin->setPrenom('System');
        $admin->setTelephone('0123456789');
        $admin->setGenre('M');
        $admin->setVille('Tunis');
        $admin->setAdresse('123 Rue Admin, Tunis');
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
        $admin->setRoles(['ROLE_SUPER_ADMIN']);
        $manager->persist($admin);

        $adminProfile = new AdminProfile();
        $adminProfile->setUser($admin);
        $adminProfile->setRolesInternes(['system_admin', 'financial_manager']);
        $adminProfile->setNotesInterne('Administrateur principal du système');
        $manager->persist($adminProfile);

        // Create Kitchen User
        $chef = new User();
        $chef->setEmail('chef@joodkitchen.com');
        $chef->setNom('Belkacem');
        $chef->setPrenom('Ahmed');
        $chef->setTelephone('0198765432');
        $chef->setGenre('M');
        $chef->setVille('Tunis');
        $chef->setAdresse('456 Rue Cuisine, Tunis');
        $chef->setPassword($this->passwordHasher->hashPassword($chef, 'chef123'));
        $chef->setRoles(['ROLE_KITCHEN']);
        $manager->persist($chef);

        $kitchenProfile = new KitchenProfile();
        $kitchenProfile->setUser($chef);
        $kitchenProfile->setPosteCuisine('Chef Principal');
        $kitchenProfile->setDisponibilite('Lundi-Vendredi: 8h-18h');
        $manager->persist($kitchenProfile);

        // Create Client User
        $client = new User();
        $client->setEmail('client@joodkitchen.com');
        $client->setNom('Ben Ali');
        $client->setPrenom('Fatma');
        $client->setTelephone('0187654321');
        $client->setGenre('F');
        $client->setVille('Tunis');
        $client->setAdresse('789 Rue Client, Tunis');
        $client->setDateNaissance(new \DateTime('1990-05-15'));
        $client->setPassword($this->passwordHasher->hashPassword($client, 'client123'));
        $client->setRoles(['ROLE_CLIENT']);
        $manager->persist($client);

        $clientProfile = new ClientProfile();
        $clientProfile->setUser($client);
        $clientProfile->setAdresseLivraison('789 Rue Client, Tunis');
        $clientProfile->setPointsFidelite(150);
        $manager->persist($clientProfile);

        // Create Plats
        $plats = [
            [
                'nom' => 'Couscous Royal',
                'description' => 'Couscous traditionnel avec agneau, bœuf et merguez',
                'prix' => '25.00',
                'categorie' => 'Plat Principal',
                'tempsPreparation' => 45,
                'allergenes' => 'Gluten'
            ],
            [
                'nom' => 'Tajine de Poulet aux Olives',
                'description' => 'Tajine traditionnel au poulet avec olives et citron confit',
                'prix' => '18.00',
                'categorie' => 'Plat Principal',
                'tempsPreparation' => 35,
                'allergenes' => null
            ],
            [
                'nom' => 'Salade Méchouia',
                'description' => 'Salade grillée tunisienne aux légumes',
                'prix' => '8.00',
                'categorie' => 'Entrée',
                'tempsPreparation' => 15,
                'allergenes' => null
            ],
            [
                'nom' => 'Poisson Grillé',
                'description' => 'Poisson frais grillé avec légumes de saison',
                'prix' => '22.00',
                'categorie' => 'Plat Principal',
                'tempsPreparation' => 25,
                'allergenes' => 'Poisson'
            ],
            [
                'nom' => 'Makroudh',
                'description' => 'Pâtisserie traditionnelle aux dattes',
                'prix' => '6.00',
                'categorie' => 'Dessert',
                'tempsPreparation' => 10,
                'allergenes' => 'Gluten, Fruits à coque'
            ]
        ];

        $platEntities = [];
        foreach ($plats as $platData) {
            $plat = new Plat();
            $plat->setNom($platData['nom']);
            $plat->setDescription($platData['description']);
            $plat->setPrix($platData['prix']);
            $plat->setCategorie($platData['categorie']);
            $plat->setTempsPreparation($platData['tempsPreparation']);
            $plat->setAllergenes($platData['allergenes']);
            $plat->setDisponible(true);
            $manager->persist($plat);
            $platEntities[] = $plat;
        }

        // Create Menus
        $menuComplet = new Menu();
        $menuComplet->setNom('Menu Complet Traditionnel');
        $menuComplet->setDescription('Menu complet avec entrée, plat principal et dessert');
        $menuComplet->setType('normal');
        $menuComplet->setPrix('35.00');
        $menuComplet->setTag('traditionnel');
        $menuComplet->setActif(true);
        $manager->persist($menuComplet);

        // Add plats to menu
        $menuPlat1 = new MenuPlat();
        $menuPlat1->setMenu($menuComplet);
        $menuPlat1->setPlat($platEntities[2]); // Salade Méchouia
        $menuPlat1->setOrdre(1);
        $manager->persist($menuPlat1);

        $menuPlat2 = new MenuPlat();
        $menuPlat2->setMenu($menuComplet);
        $menuPlat2->setPlat($platEntities[0]); // Couscous Royal
        $menuPlat2->setOrdre(2);
        $manager->persist($menuPlat2);

        $menuPlat3 = new MenuPlat();
        $menuPlat3->setMenu($menuComplet);
        $menuPlat3->setPlat($platEntities[4]); // Makroudh
        $menuPlat3->setOrdre(3);
        $manager->persist($menuPlat3);

        // Create Menu du Jour
        $menuDuJour = new Menu();
        $menuDuJour->setNom('Menu du Jour');
        $menuDuJour->setDescription('Spécialité du chef du jour');
        $menuDuJour->setType('menu_du_jour');
        $menuDuJour->setDate(new \DateTime());
        $menuDuJour->setPrix('20.00');
        $menuDuJour->setActif(true);
        $manager->persist($menuDuJour);

        $menuPlatJour = new MenuPlat();
        $menuPlatJour->setMenu($menuDuJour);
        $menuPlatJour->setPlat($platEntities[1]); // Tajine de Poulet
        $menuPlatJour->setOrdre(1);
        $manager->persist($menuPlatJour);

        // Create a sample order
        $commande = new Commande();
        $commande->setUser($client);
        $commande->setDateCommande(new \DateTime());
        $commande->setStatut('confirmee');
        $manager->persist($commande);

        // Add items to order
        $commandeArticle1 = new CommandeArticle();
        $commandeArticle1->setCommande($commande);
        $commandeArticle1->setPlat($platEntities[0]); // Couscous Royal
        $commandeArticle1->setQuantite(2);
        $commandeArticle1->setPrixUnitaire('25.00');
        $manager->persist($commandeArticle1);

        $commandeArticle2 = new CommandeArticle();
        $commandeArticle2->setCommande($commande);
        $commandeArticle2->setPlat($platEntities[2]); // Salade Méchouia
        $commandeArticle2->setQuantite(1);
        $commandeArticle2->setPrixUnitaire('8.00');
        $manager->persist($commandeArticle2);

        // Calculate and set total
        $commande->calculateTotal();

        $manager->flush();
    }
} 