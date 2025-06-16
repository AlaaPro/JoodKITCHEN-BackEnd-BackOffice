<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250616110236 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE audit_associations (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, typ VARCHAR(128) NOT NULL, tbl VARCHAR(128) DEFAULT NULL, label VARCHAR(255) DEFAULT NULL, fk INT UNSIGNED NOT NULL, class VARCHAR(255) NOT NULL, INDEX IDX_969DC4BA81E660E (fk), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE audit_logs (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, source_id BIGINT UNSIGNED NOT NULL, target_id BIGINT UNSIGNED DEFAULT NULL, blame_id BIGINT UNSIGNED DEFAULT NULL, action VARCHAR(12) NOT NULL, tbl VARCHAR(128) NOT NULL, diff JSON DEFAULT NULL COMMENT '(DC2Type:json)', logged_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_D62F2858953C1C61 (source_id), UNIQUE INDEX UNIQ_D62F2858158E0B66 (target_id), UNIQUE INDEX UNIQ_D62F28588C082A2E (blame_id), INDEX IDX_D62F2858A78D87A7 (logged_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE audit_logs ADD CONSTRAINT FK_D62F2858953C1C61 FOREIGN KEY (source_id) REFERENCES audit_associations (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE audit_logs ADD CONSTRAINT FK_D62F2858158E0B66 FOREIGN KEY (target_id) REFERENCES audit_associations (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE audit_logs ADD CONSTRAINT FK_D62F28588C082A2E FOREIGN KEY (blame_id) REFERENCES audit_associations (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE audit_logs DROP FOREIGN KEY FK_D62F2858953C1C61
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE audit_logs DROP FOREIGN KEY FK_D62F2858158E0B66
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE audit_logs DROP FOREIGN KEY FK_D62F28588C082A2E
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE audit_associations
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE audit_logs
        SQL);
    }
}
