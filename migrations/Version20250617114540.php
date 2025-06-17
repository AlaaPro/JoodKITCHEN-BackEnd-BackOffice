<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250617114540 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE kitchen_profile_roles (kitchen_profile_id INT NOT NULL, role_id INT NOT NULL, INDEX IDX_71331F539E4D63CC (kitchen_profile_id), INDEX IDX_71331F53D60322AC (role_id), PRIMARY KEY(kitchen_profile_id, role_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE kitchen_profile_permissions (kitchen_profile_id INT NOT NULL, permission_id INT NOT NULL, INDEX IDX_51D151149E4D63CC (kitchen_profile_id), INDEX IDX_51D15114FED90CCA (permission_id), PRIMARY KEY(kitchen_profile_id, permission_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE kitchen_profile_roles ADD CONSTRAINT FK_71331F539E4D63CC FOREIGN KEY (kitchen_profile_id) REFERENCES kitchen_profile (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE kitchen_profile_roles ADD CONSTRAINT FK_71331F53D60322AC FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE kitchen_profile_permissions ADD CONSTRAINT FK_51D151149E4D63CC FOREIGN KEY (kitchen_profile_id) REFERENCES kitchen_profile (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE kitchen_profile_permissions ADD CONSTRAINT FK_51D15114FED90CCA FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE kitchen_profile ADD specialites JSON NOT NULL COMMENT '(DC2Type:json)', ADD certifications JSON DEFAULT NULL COMMENT '(DC2Type:json)', ADD horaire_travail JSON DEFAULT NULL COMMENT '(DC2Type:json)', ADD permissions_kitchen JSON DEFAULT NULL COMMENT '(DC2Type:json)', ADD notes_interne LONGTEXT DEFAULT NULL, ADD statut_travail VARCHAR(50) DEFAULT NULL, ADD experience_annees INT DEFAULT NULL, ADD salaire_horaire NUMERIC(10, 2) DEFAULT NULL, ADD heures_par_semaine INT DEFAULT NULL, ADD date_embauche DATE DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE kitchen_profile_roles DROP FOREIGN KEY FK_71331F539E4D63CC
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE kitchen_profile_roles DROP FOREIGN KEY FK_71331F53D60322AC
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE kitchen_profile_permissions DROP FOREIGN KEY FK_51D151149E4D63CC
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE kitchen_profile_permissions DROP FOREIGN KEY FK_51D15114FED90CCA
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE kitchen_profile_roles
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE kitchen_profile_permissions
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE kitchen_profile DROP specialites, DROP certifications, DROP horaire_travail, DROP permissions_kitchen, DROP notes_interne, DROP statut_travail, DROP experience_annees, DROP salaire_horaire, DROP heures_par_semaine, DROP date_embauche
        SQL);
    }
}
