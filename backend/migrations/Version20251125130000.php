<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Allow NULL for mutation_type and notes (for savings account CSV import).
 */
final class Version20251125130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow NULL for mutation_type and notes columns';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE transaction MODIFY mutation_type VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE transaction MODIFY notes TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE transaction MODIFY mutation_type VARCHAR(100) NOT NULL DEFAULT ''");
        $this->addSql("ALTER TABLE transaction MODIFY notes TEXT NOT NULL");
    }
}
