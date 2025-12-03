<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251203220801 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add parent_account_id to link savings accounts to checking accounts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE account ADD parent_account_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE account ADD CONSTRAINT FK_7D3656A4B23A8E7 FOREIGN KEY (parent_account_id) REFERENCES account (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_7D3656A4B23A8E7 ON account (parent_account_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE account DROP FOREIGN KEY FK_7D3656A4B23A8E7');
        $this->addSql('DROP INDEX IDX_7D3656A4B23A8E7 ON account');
        $this->addSql('ALTER TABLE account DROP parent_account_id');
    }
}
