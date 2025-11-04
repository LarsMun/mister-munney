<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251103211742 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add project support to budgets: add description, start_date, end_date, status fields to budget table and create external_payment table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE external_payment (id INT AUTO_INCREMENT NOT NULL, budget_id INT NOT NULL, amount INT NOT NULL, paid_on DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', payer_source VARCHAR(255) NOT NULL, note LONGTEXT NOT NULL, attachment_url VARCHAR(500) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_B48A5D4E36ABA6B8 (budget_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE external_payment ADD CONSTRAINT FK_B48A5D4E36ABA6B8 FOREIGN KEY (budget_id) REFERENCES budget (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE budget ADD description LONGTEXT DEFAULT NULL, ADD start_date DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', ADD end_date DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', ADD status VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE external_payment DROP FOREIGN KEY FK_B48A5D4E36ABA6B8');
        $this->addSql('DROP TABLE external_payment');
        $this->addSql('ALTER TABLE budget DROP description, DROP start_date, DROP end_date, DROP status');
    }
}
