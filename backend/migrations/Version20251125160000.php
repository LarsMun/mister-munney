<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create forecast_item table for cashflow forecast feature.
 */
final class Version20251125160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create forecast_item table for cashflow forecast';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE forecast_item (
            id INT AUTO_INCREMENT NOT NULL,
            account_id INT NOT NULL,
            budget_id INT DEFAULT NULL,
            category_id INT DEFAULT NULL,
            type VARCHAR(20) NOT NULL,
            expected_amount_in_cents INT NOT NULL,
            position INT DEFAULT 0 NOT NULL,
            custom_name VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_FORECAST_ACCOUNT (account_id),
            INDEX IDX_FORECAST_BUDGET (budget_id),
            INDEX IDX_FORECAST_CATEGORY (category_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_FORECAST_ACCOUNT FOREIGN KEY (account_id) REFERENCES account (id) ON DELETE CASCADE,
            CONSTRAINT FK_FORECAST_BUDGET FOREIGN KEY (budget_id) REFERENCES budget (id) ON DELETE CASCADE,
            CONSTRAINT FK_FORECAST_CATEGORY FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE forecast_item');
    }
}
