<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250709194542 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE order_status_history (id INT AUTO_INCREMENT NOT NULL, commande_id INT NOT NULL, changed_by_id INT DEFAULT NULL, status VARCHAR(50) NOT NULL, previous_status VARCHAR(50) DEFAULT NULL, created_at DATETIME NOT NULL, comment LONGTEXT DEFAULT NULL, INDEX IDX_471AD77E82EA2E54 (commande_id), INDEX IDX_471AD77E828AD0A0 (changed_by_id), INDEX idx_order_status (commande_id, status), INDEX idx_created_at (created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE order_status_history ADD CONSTRAINT FK_471AD77E82EA2E54 FOREIGN KEY (commande_id) REFERENCES commande (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE order_status_history ADD CONSTRAINT FK_471AD77E828AD0A0 FOREIGN KEY (changed_by_id) REFERENCES `user` (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE order_status_history DROP FOREIGN KEY FK_471AD77E82EA2E54
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE order_status_history DROP FOREIGN KEY FK_471AD77E828AD0A0
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE order_status_history
        SQL);
    }
}
