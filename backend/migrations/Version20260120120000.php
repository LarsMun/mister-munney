<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260120120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create recurring_transaction table for detecting recurring payments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE recurring_transaction (
            id INT AUTO_INCREMENT NOT NULL,
            account_id INT NOT NULL,
            category_id INT DEFAULT NULL,
            merchant_pattern VARCHAR(255) NOT NULL,
            display_name VARCHAR(255) NOT NULL,
            predicted_amount INT NOT NULL,
            amount_variance DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
            frequency VARCHAR(20) NOT NULL,
            confidence_score DECIMAL(3, 2) NOT NULL,
            last_occurrence DATE DEFAULT NULL,
            next_expected DATE DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            occurrence_count INT NOT NULL DEFAULT 0,
            interval_consistency DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
            transaction_type VARCHAR(10) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_recurring_account (account_id),
            INDEX idx_recurring_frequency (frequency),
            INDEX idx_recurring_active (is_active),
            INDEX idx_recurring_next_expected (next_expected),
            INDEX idx_recurring_merchant (merchant_pattern),
            PRIMARY KEY(id),
            CONSTRAINT FK_recurring_account FOREIGN KEY (account_id) REFERENCES account (id) ON DELETE CASCADE,
            CONSTRAINT FK_recurring_category FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE recurring_transaction');
    }
}
