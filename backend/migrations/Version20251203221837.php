<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251203221837 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make transaction_code nullable for savings account imports';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE transaction MODIFY transaction_code VARCHAR(10) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE transaction SET transaction_code = '' WHERE transaction_code IS NULL");
        $this->addSql('ALTER TABLE transaction MODIFY transaction_code VARCHAR(10) NOT NULL');
    }
}
