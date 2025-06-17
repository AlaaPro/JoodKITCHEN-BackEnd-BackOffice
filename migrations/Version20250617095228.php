<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250617095228 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE abonnement_selection (id INT AUTO_INCREMENT NOT NULL, abonnement_id INT NOT NULL, menu_id INT DEFAULT NULL, plat_id INT DEFAULT NULL, date_repas DATE NOT NULL, jour_semaine VARCHAR(20) NOT NULL, type_selection VARCHAR(20) NOT NULL, cuisine_type VARCHAR(50) DEFAULT NULL, prix NUMERIC(10, 2) NOT NULL, statut VARCHAR(50) NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_8E51421BF1D74413 (abonnement_id), INDEX IDX_8E51421BCCD7E912 (menu_id), INDEX IDX_8E51421BD73DB560 (plat_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE abonnement_selection ADD CONSTRAINT FK_8E51421BF1D74413 FOREIGN KEY (abonnement_id) REFERENCES abonnement (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE abonnement_selection ADD CONSTRAINT FK_8E51421BCCD7E912 FOREIGN KEY (menu_id) REFERENCES menu (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE abonnement_selection ADD CONSTRAINT FK_8E51421BD73DB560 FOREIGN KEY (plat_id) REFERENCES plat (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE payment ADD abonnement_id INT DEFAULT NULL, ADD type_paiement VARCHAR(50) DEFAULT NULL, CHANGE commande_id commande_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE payment ADD CONSTRAINT FK_6D28840DF1D74413 FOREIGN KEY (abonnement_id) REFERENCES abonnement (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_6D28840DF1D74413 ON payment (abonnement_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE abonnement_selection DROP FOREIGN KEY FK_8E51421BF1D74413
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE abonnement_selection DROP FOREIGN KEY FK_8E51421BCCD7E912
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE abonnement_selection DROP FOREIGN KEY FK_8E51421BD73DB560
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE abonnement_selection
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE payment DROP FOREIGN KEY FK_6D28840DF1D74413
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_6D28840DF1D74413 ON payment
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE payment DROP abonnement_id, DROP type_paiement, CHANGE commande_id commande_id INT NOT NULL
        SQL);
    }
}
