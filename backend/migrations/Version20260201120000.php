<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260201120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_temporary column to transaction table and make balance_after nullable';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE transaction ADD is_temporary TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE transaction MODIFY balance_after INT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_transaction_account_temporary ON transaction (account_id, is_temporary)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_transaction_account_temporary ON transaction');
        $this->addSql('ALTER TABLE transaction MODIFY balance_after INT NOT NULL');
        $this->addSql('ALTER TABLE transaction DROP is_temporary');
    }
}
