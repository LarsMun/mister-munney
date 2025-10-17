<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251017070454 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ai_pattern_suggestion (id INT AUTO_INCREMENT NOT NULL, account_id INT NOT NULL, existing_category_id INT DEFAULT NULL, created_pattern_id INT DEFAULT NULL, description_pattern VARCHAR(255) DEFAULT NULL, notes_pattern VARCHAR(255) DEFAULT NULL, suggested_category_name VARCHAR(100) NOT NULL, match_count INT NOT NULL, confidence DOUBLE PRECISION NOT NULL, reasoning LONGTEXT DEFAULT NULL, example_transactions JSON NOT NULL, status VARCHAR(255) NOT NULL, pattern_hash VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', processed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_B3F1F7F7955D11 (pattern_hash), INDEX IDX_B3F1F7F9B6B5FBA (account_id), INDEX IDX_B3F1F7F6E2100B8 (existing_category_id), INDEX IDX_B3F1F7F56B0ABAE (created_pattern_id), INDEX IDX_B3F1F7F9B6B5FBA7B00651C (account_id, status), INDEX IDX_B3F1F7F7955D11 (pattern_hash), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE ai_pattern_suggestion ADD CONSTRAINT FK_B3F1F7F9B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ai_pattern_suggestion ADD CONSTRAINT FK_B3F1F7F6E2100B8 FOREIGN KEY (existing_category_id) REFERENCES category (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE ai_pattern_suggestion ADD CONSTRAINT FK_B3F1F7F56B0ABAE FOREIGN KEY (created_pattern_id) REFERENCES pattern (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ai_pattern_suggestion DROP FOREIGN KEY FK_B3F1F7F9B6B5FBA');
        $this->addSql('ALTER TABLE ai_pattern_suggestion DROP FOREIGN KEY FK_B3F1F7F6E2100B8');
        $this->addSql('ALTER TABLE ai_pattern_suggestion DROP FOREIGN KEY FK_B3F1F7F56B0ABAE');
        $this->addSql('DROP TABLE ai_pattern_suggestion');
    }
}
