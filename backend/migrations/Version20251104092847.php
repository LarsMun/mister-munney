<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251104092847 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add duration_months field to budget table for automatic status calculation';
    }

    public function up(Schema $schema): void
    {
        // Add duration_months column to budget table
        $this->addSql('ALTER TABLE budget ADD duration_months INT DEFAULT 2 NOT NULL COMMENT \'Number of months a project remains active after last payment\'');
    }

    public function down(Schema $schema): void
    {
        // Remove duration_months column
        $this->addSql('ALTER TABLE budget DROP duration_months');
    }
}
