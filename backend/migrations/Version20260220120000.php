<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_active column to budget table for manual active/inactive toggle';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE budget ADD is_active TINYINT(1) NOT NULL DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE budget DROP is_active');
    }
}
