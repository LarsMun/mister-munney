<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251107095856 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop budget_version table - budgets are now just containers for categories without version history';
    }

    public function up(Schema $schema): void
    {
        // Drop budget_version table - budgets no longer have amounts or versions
        $this->addSql('DROP TABLE budget_version');
    }

    public function down(Schema $schema): void
    {
        // Recreate budget_version table for rollback
        $this->addSql('CREATE TABLE budget_version (
            id INT AUTO_INCREMENT NOT NULL,
            budget_id INT NOT NULL,
            monthly_amount INT NOT NULL,
            effective_from_month VARCHAR(7) NOT NULL,
            effective_until_month VARCHAR(7) DEFAULT NULL,
            change_reason LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_36AE048E8CBA5C1 (budget_id),
            INDEX idx_effective_from (effective_from_month),
            INDEX idx_effective_until (effective_until_month),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE budget_version ADD CONSTRAINT FK_36AE048E8CBA5C1 FOREIGN KEY (budget_id) REFERENCES budget (id) ON DELETE CASCADE');
    }
}
