<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250530095341 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE abonnement (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, type VARCHAR(50) NOT NULL, repas_par_jour INT NOT NULL, date_debut DATE NOT NULL, date_fin DATE NOT NULL, statut VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_351268BBA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE admin_profile (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, roles_internes JSON NOT NULL COMMENT '(DC2Type:json)', permissions_avancees JSON DEFAULT NULL COMMENT '(DC2Type:json)', notes_interne LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_456B2886A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE client_profile (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, adresse_livraison LONGTEXT DEFAULT NULL, points_fidelite INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_D36AEE72A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE commande (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, date_commande DATETIME NOT NULL, statut VARCHAR(50) NOT NULL, total NUMERIC(10, 2) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, total_avant_reduction NUMERIC(10, 2) DEFAULT NULL, INDEX IDX_6EEAA67DA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE commande_article (id INT AUTO_INCREMENT NOT NULL, commande_id INT NOT NULL, plat_id INT DEFAULT NULL, menu_id INT DEFAULT NULL, quantite INT NOT NULL, prix_unitaire NUMERIC(10, 2) NOT NULL, commentaire LONGTEXT DEFAULT NULL, INDEX IDX_F4817CC682EA2E54 (commande_id), INDEX IDX_F4817CC6D73DB560 (plat_id), INDEX IDX_F4817CC6CCD7E912 (menu_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE commande_reduction (id INT AUTO_INCREMENT NOT NULL, commande_id INT NOT NULL, type VARCHAR(50) NOT NULL, valeur NUMERIC(10, 2) NOT NULL, source VARCHAR(255) NOT NULL, code_promo VARCHAR(255) DEFAULT NULL, commentaire LONGTEXT DEFAULT NULL, INDEX IDX_A1EB0E2A82EA2E54 (commande_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE fidelite_point_history (id INT AUTO_INCREMENT NOT NULL, client_profile_id INT NOT NULL, commande_id INT DEFAULT NULL, type VARCHAR(50) NOT NULL, points INT NOT NULL, source VARCHAR(255) NOT NULL, date DATETIME NOT NULL, commentaire LONGTEXT DEFAULT NULL, INDEX IDX_8ED62765CAE2FF9 (client_profile_id), INDEX IDX_8ED627682EA2E54 (commande_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE kitchen_profile (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, poste_cuisine VARCHAR(255) NOT NULL, disponibilite LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_95564E1DA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE menu (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, type VARCHAR(50) NOT NULL, jour_semaine VARCHAR(50) DEFAULT NULL, date DATE DEFAULT NULL, prix NUMERIC(10, 2) NOT NULL, tag VARCHAR(255) DEFAULT NULL, actif TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE menu_plat (id INT AUTO_INCREMENT NOT NULL, menu_id INT NOT NULL, plat_id INT NOT NULL, ordre INT NOT NULL, INDEX IDX_E8775249CCD7E912 (menu_id), INDEX IDX_E8775249D73DB560 (plat_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE payment (id INT AUTO_INCREMENT NOT NULL, commande_id INT NOT NULL, montant NUMERIC(10, 2) NOT NULL, methode_paiement VARCHAR(50) NOT NULL, statut VARCHAR(50) NOT NULL, reference_transaction VARCHAR(255) DEFAULT NULL, date_paiement DATETIME NOT NULL, commentaire LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_6D28840D82EA2E54 (commande_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE plat (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, prix NUMERIC(10, 2) NOT NULL, categorie VARCHAR(255) NOT NULL, image LONGTEXT DEFAULT NULL, disponible TINYINT(1) NOT NULL, allergenes LONGTEXT DEFAULT NULL, temps_preparation INT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL COMMENT '(DC2Type:json)', password VARCHAR(255) NOT NULL, is_active TINYINT(1) NOT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, telephone VARCHAR(20) NOT NULL, photo_profil LONGTEXT DEFAULT NULL, genre VARCHAR(10) NOT NULL, date_naissance DATE DEFAULT NULL, ville VARCHAR(255) DEFAULT NULL, adresse LONGTEXT DEFAULT NULL, last_connexion DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE abonnement ADD CONSTRAINT FK_351268BBA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE admin_profile ADD CONSTRAINT FK_456B2886A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE client_profile ADD CONSTRAINT FK_D36AEE72A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67DA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE commande_article ADD CONSTRAINT FK_F4817CC682EA2E54 FOREIGN KEY (commande_id) REFERENCES commande (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE commande_article ADD CONSTRAINT FK_F4817CC6D73DB560 FOREIGN KEY (plat_id) REFERENCES plat (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE commande_article ADD CONSTRAINT FK_F4817CC6CCD7E912 FOREIGN KEY (menu_id) REFERENCES menu (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE commande_reduction ADD CONSTRAINT FK_A1EB0E2A82EA2E54 FOREIGN KEY (commande_id) REFERENCES commande (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE fidelite_point_history ADD CONSTRAINT FK_8ED62765CAE2FF9 FOREIGN KEY (client_profile_id) REFERENCES client_profile (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE fidelite_point_history ADD CONSTRAINT FK_8ED627682EA2E54 FOREIGN KEY (commande_id) REFERENCES commande (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE kitchen_profile ADD CONSTRAINT FK_95564E1DA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE menu_plat ADD CONSTRAINT FK_E8775249CCD7E912 FOREIGN KEY (menu_id) REFERENCES menu (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE menu_plat ADD CONSTRAINT FK_E8775249D73DB560 FOREIGN KEY (plat_id) REFERENCES plat (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE payment ADD CONSTRAINT FK_6D28840D82EA2E54 FOREIGN KEY (commande_id) REFERENCES commande (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE abonnement DROP FOREIGN KEY FK_351268BBA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE admin_profile DROP FOREIGN KEY FK_456B2886A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE client_profile DROP FOREIGN KEY FK_D36AEE72A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE commande DROP FOREIGN KEY FK_6EEAA67DA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE commande_article DROP FOREIGN KEY FK_F4817CC682EA2E54
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE commande_article DROP FOREIGN KEY FK_F4817CC6D73DB560
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE commande_article DROP FOREIGN KEY FK_F4817CC6CCD7E912
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE commande_reduction DROP FOREIGN KEY FK_A1EB0E2A82EA2E54
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE fidelite_point_history DROP FOREIGN KEY FK_8ED62765CAE2FF9
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE fidelite_point_history DROP FOREIGN KEY FK_8ED627682EA2E54
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE kitchen_profile DROP FOREIGN KEY FK_95564E1DA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE menu_plat DROP FOREIGN KEY FK_E8775249CCD7E912
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE menu_plat DROP FOREIGN KEY FK_E8775249D73DB560
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D82EA2E54
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE abonnement
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE admin_profile
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE client_profile
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE commande
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE commande_article
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE commande_reduction
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE fidelite_point_history
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE kitchen_profile
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE menu
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE menu_plat
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE payment
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE plat
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE `user`
        SQL);
    }
}
