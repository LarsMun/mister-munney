<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251103212230 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create feature_flag table and seed initial adaptive dashboard feature flags';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE feature_flag (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, enabled TINYINT(1) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_83DE64E95E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Seed initial feature flags (all enabled by default in dev)
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->addSql("INSERT INTO feature_flag (name, enabled, description, created_at, updated_at) VALUES
            ('living_dashboard', 1, 'Show only active budgets on dashboard with older budgets collapsible panel', '{$now}', '{$now}'),
            ('projects', 1, 'Enable project-type budgets for tracking temporary expenses', '{$now}', '{$now}'),
            ('external_payments', 1, 'Allow adding off-ledger payments to projects', '{$now}', '{$now}'),
            ('behavioral_insights', 1, 'Show behavioral insights comparing current spend to rolling median', '{$now}', '{$now}')
        ");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE feature_flag');
    }
}
