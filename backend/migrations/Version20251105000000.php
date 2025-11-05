<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add transaction splits support - parent_transaction_id column
 */
final class Version20251105000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add parent_transaction_id column to transaction table for split transaction support';
    }

    public function up(Schema $schema): void
    {
        // Add parent_transaction_id column to transaction table
        $this->addSql('ALTER TABLE transaction ADD parent_transaction_id INT DEFAULT NULL');

        // Add foreign key constraint with CASCADE on delete
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1C19C5E47 FOREIGN KEY (parent_transaction_id) REFERENCES transaction (id) ON DELETE CASCADE');

        // Add index for performance
        $this->addSql('CREATE INDEX IDX_723705D1C19C5E47 ON transaction (parent_transaction_id)');
    }

    public function down(Schema $schema): void
    {
        // Remove foreign key constraint
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D1C19C5E47');

        // Remove index
        $this->addSql('DROP INDEX IDX_723705D1C19C5E47 ON transaction');

        // Remove column
        $this->addSql('ALTER TABLE transaction DROP parent_transaction_id');
    }
}
