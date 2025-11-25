<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add account type field to distinguish between checking and savings accounts.
 */
final class Version20251125110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add type column to account table (CHECKING/SAVINGS)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE account ADD type VARCHAR(255) NOT NULL DEFAULT 'CHECKING'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE account DROP COLUMN type');
    }
}
