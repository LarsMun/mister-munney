<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251113202926 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add login attempt tracking and account lock functionality';
    }

    public function up(Schema $schema): void
    {
        // Create login_attempts table
        $this->addSql('CREATE TABLE login_attempts (
            id INT AUTO_INCREMENT NOT NULL,
            email VARCHAR(255) NOT NULL,
            attempted_at DATETIME NOT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent TEXT DEFAULT NULL,
            success TINYINT(1) DEFAULT 0 NOT NULL,
            PRIMARY KEY(id),
            INDEX idx_email_time (email, attempted_at)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Add lock fields to user table
        $this->addSql('ALTER TABLE user ADD is_locked TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE user ADD locked_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD unlock_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD unlock_token_expires_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Remove lock fields from user table
        $this->addSql('ALTER TABLE user DROP is_locked');
        $this->addSql('ALTER TABLE user DROP locked_at');
        $this->addSql('ALTER TABLE user DROP unlock_token');
        $this->addSql('ALTER TABLE user DROP unlock_token_expires_at');

        // Drop login_attempts table
        $this->addSql('DROP TABLE login_attempts');
    }
}
