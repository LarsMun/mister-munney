<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260119120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create audit_logs table for security tracking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE audit_logs (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT DEFAULT NULL,
            action VARCHAR(50) NOT NULL,
            entity_type VARCHAR(100) DEFAULT NULL,
            entity_id INT DEFAULT NULL,
            details JSON DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_audit_user (user_id),
            INDEX idx_audit_action (action),
            INDEX idx_audit_created (created_at),
            INDEX idx_audit_entity (entity_type, entity_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_audit_user FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE audit_logs');
    }
}
