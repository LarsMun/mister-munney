<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Remove SavingsAccount entity and all references to it.
 * Savings will be handled via Account types (CHECKING/SAVINGS) instead.
 */
final class Version20251125100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove SavingsAccount entity - savings will be tracked via Account types';
    }

    public function up(Schema $schema): void
    {
        // First, drop foreign key constraints
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY IF EXISTS FK_723705D13B727EAE');
        $this->addSql('ALTER TABLE pattern DROP FOREIGN KEY IF EXISTS FK_A44E7B143B727EAE');

        // Remove savings_account_id columns
        $this->addSql('ALTER TABLE transaction DROP COLUMN IF EXISTS savings_account_id');
        $this->addSql('ALTER TABLE pattern DROP COLUMN IF EXISTS savings_account_id');

        // Drop the savings_account table
        $this->addSql('DROP TABLE IF EXISTS savings_account');
    }

    public function down(Schema $schema): void
    {
        // Recreate savings_account table
        $this->addSql('CREATE TABLE savings_account (
            id INT AUTO_INCREMENT NOT NULL,
            account_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            target_amount DECIMAL(10, 2) DEFAULT NULL,
            color VARCHAR(7) DEFAULT \'#CCCCCC\' NOT NULL,
            UNIQUE INDEX unique_savings_account (name, account_id),
            INDEX IDX_E5EE05A09B6B5FBA (account_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Add back the foreign key for savings_account to account
        $this->addSql('ALTER TABLE savings_account ADD CONSTRAINT FK_E5EE05A09B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) ON DELETE CASCADE');

        // Add savings_account_id back to transaction
        $this->addSql('ALTER TABLE transaction ADD savings_account_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D13B727EAE FOREIGN KEY (savings_account_id) REFERENCES savings_account (id) ON DELETE SET NULL');

        // Add savings_account_id back to pattern
        $this->addSql('ALTER TABLE pattern ADD savings_account_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE pattern ADD CONSTRAINT FK_A44E7B143B727EAE FOREIGN KEY (savings_account_id) REFERENCES savings_account (id) ON DELETE SET NULL');
    }
}
