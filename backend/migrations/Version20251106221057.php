<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251106221057 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_USER_EMAIL (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_account (user_id INT NOT NULL, account_id INT NOT NULL, INDEX IDX_253B48AEA76ED395 (user_id), INDEX IDX_253B48AE9B6B5FBA (account_id), PRIMARY KEY(user_id, account_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_account ADD CONSTRAINT FK_253B48AEA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_account ADD CONSTRAINT FK_253B48AE9B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE budget CHANGE duration_months duration_months INT DEFAULT 2 NOT NULL');
        $this->addSql('ALTER TABLE transaction RENAME INDEX idx_723705d1c19c5e47 TO IDX_723705D1311DBF04');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_account DROP FOREIGN KEY FK_253B48AEA76ED395');
        $this->addSql('ALTER TABLE user_account DROP FOREIGN KEY FK_253B48AE9B6B5FBA');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE user_account');
        $this->addSql('ALTER TABLE budget CHANGE duration_months duration_months INT DEFAULT 2 NOT NULL COMMENT \'Number of months a project remains active after last payment\'');
        $this->addSql('ALTER TABLE transaction RENAME INDEX idx_723705d1311dbf04 TO IDX_723705D1C19C5E47');
    }
}
