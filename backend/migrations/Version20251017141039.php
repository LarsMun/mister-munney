<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251017141039 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add budget_type enum to budget table, default existing budgets to EXPENSE';
    }

    public function up(Schema $schema): void
    {
        // Add budget_type column with EXPENSE as default
        $this->addSql("ALTER TABLE budget ADD budget_type VARCHAR(255) NOT NULL DEFAULT 'EXPENSE'");
    }

    public function down(Schema $schema): void
    {
        // Remove budget_type column
        $this->addSql('ALTER TABLE budget DROP budget_type');
    }
}
