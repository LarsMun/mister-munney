<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add performance indexes for common query patterns
 */
final class Version20251205222936 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add performance indexes for transaction date queries';
    }

    public function up(Schema $schema): void
    {
        // Composite index for account + date range queries (very common pattern)
        $this->addSql('CREATE INDEX idx_transaction_account_date ON transaction (account_id, date)');

        // Index on date for date range queries
        $this->addSql('CREATE INDEX idx_transaction_date ON transaction (date)');

        // Index on transaction_type for filtering by type
        $this->addSql('CREATE INDEX idx_transaction_type ON transaction (transaction_type)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_transaction_account_date ON transaction');
        $this->addSql('DROP INDEX idx_transaction_date ON transaction');
        $this->addSql('DROP INDEX idx_transaction_type ON transaction');
    }
}
