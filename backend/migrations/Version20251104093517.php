<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251104093517 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove status, start_date, and end_date columns from budget table';
    }

    public function up(Schema $schema): void
    {
        // Remove status, start_date, and end_date columns from budget
        $this->addSql('ALTER TABLE budget DROP status');
        $this->addSql('ALTER TABLE budget DROP start_date');
        $this->addSql('ALTER TABLE budget DROP end_date');
    }

    public function down(Schema $schema): void
    {
        // Restore removed columns
        $this->addSql('ALTER TABLE budget ADD status VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE budget ADD start_date DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\'');
        $this->addSql('ALTER TABLE budget ADD end_date DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\'');
    }
}
