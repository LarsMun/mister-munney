<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Performance optimization: Add indexes for frequently used query patterns
 */
final class Version20251206231800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add performance indexes for category and budget queries';
    }

    public function up(Schema $schema): void
    {
        // Index for category-based queries (used in 15+ queries)
        $this->addSql('CREATE INDEX idx_transaction_category_id ON transaction (category_id)');

        // Composite index for budget breakdown queries
        $this->addSql('CREATE INDEX idx_transaction_category_date ON transaction (category_id, date)');

        // Index for budget queries by account
        $this->addSql('CREATE INDEX idx_budget_account_id ON budget (account_id)');

        // Index for category queries by account
        $this->addSql('CREATE INDEX idx_category_account_id ON category (account_id)');

        // Index for pattern queries by account
        $this->addSql('CREATE INDEX idx_pattern_account_id ON pattern (account_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_transaction_category_id ON transaction');
        $this->addSql('DROP INDEX idx_transaction_category_date ON transaction');
        $this->addSql('DROP INDEX idx_budget_account_id ON budget');
        $this->addSql('DROP INDEX idx_category_account_id ON category');
        $this->addSql('DROP INDEX idx_pattern_account_id ON pattern');
    }
}
