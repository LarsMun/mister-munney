<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Add account sharing security features to user_account table
 *
 * Security Fix: Prevents unauthorized account access via CSV import
 * - Adds role system (owner vs shared users)
 * - Adds invitation workflow with expiry
 * - Migrates existing users: first user per account becomes owner, rest become shared
 */
final class Version20251107224624 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add account sharing security: role, status, invitation workflow, and data migration';
    }

    public function up(Schema $schema): void
    {
        // Step 1: Drop old composite primary key first
        $this->addSql("ALTER TABLE user_account DROP PRIMARY KEY");

        // Step 2: Add new id column as primary key and other columns
        $this->addSql("
            ALTER TABLE user_account
            ADD id INT AUTO_INCREMENT PRIMARY KEY FIRST,
            ADD role ENUM('owner', 'shared') NOT NULL DEFAULT 'shared' AFTER account_id,
            ADD status ENUM('active', 'pending', 'revoked') NOT NULL DEFAULT 'active' AFTER role,
            ADD invited_by_id INT DEFAULT NULL AFTER status,
            ADD invited_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)' AFTER invited_by_id,
            ADD accepted_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)' AFTER invited_at,
            ADD expires_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)' AFTER accepted_at,
            ADD created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '(DC2Type:datetime_immutable)' AFTER expires_at
        ");

        // Step 3: Add unique constraint on (user_id, account_id) to prevent duplicates
        $this->addSql("
            ALTER TABLE user_account
            ADD UNIQUE KEY unique_user_account (user_id, account_id)
        ");

        // Step 4: Migrate existing data - first user per account becomes owner
        // Use a subquery to find the minimum user_id for each account_id
        $this->addSql("
            UPDATE user_account ua
            INNER JOIN (
                SELECT account_id, MIN(user_id) as first_user_id
                FROM user_account
                GROUP BY account_id
            ) first_users ON ua.account_id = first_users.account_id
            SET ua.role = 'owner'
            WHERE ua.user_id = first_users.first_user_id
        ");

        // Step 5: All other users become 'shared' (already default, but explicit for clarity)
        $this->addSql("
            UPDATE user_account
            SET role = 'shared'
            WHERE role != 'owner'
        ");

        // Step 6: Set all existing relationships to 'active' status (already default)
        // No action needed as DEFAULT 'active' handles this

        // Step 7: Add foreign key for invited_by_id
        $this->addSql("
            ALTER TABLE user_account
            ADD CONSTRAINT FK_253B48AE_INVITED_BY
            FOREIGN KEY (invited_by_id) REFERENCES user (id) ON DELETE SET NULL
        ");

        // Step 8: Add index for common queries
        $this->addSql("CREATE INDEX idx_user_account_status ON user_account (status)");
        $this->addSql("CREATE INDEX idx_user_account_role ON user_account (role)");
    }

    public function down(Schema $schema): void
    {
        // Step 1: Drop foreign key
        $this->addSql("ALTER TABLE user_account DROP FOREIGN KEY FK_253B48AE_INVITED_BY");

        // Step 2: Drop indexes
        $this->addSql("DROP INDEX idx_user_account_status ON user_account");
        $this->addSql("DROP INDEX idx_user_account_role ON user_account");

        // Step 3: Drop unique constraint
        $this->addSql("ALTER TABLE user_account DROP INDEX unique_user_account");

        // Step 4: Remove new columns
        $this->addSql("
            ALTER TABLE user_account
            DROP COLUMN id,
            DROP COLUMN role,
            DROP COLUMN status,
            DROP COLUMN invited_by_id,
            DROP COLUMN invited_at,
            DROP COLUMN accepted_at,
            DROP COLUMN expires_at,
            DROP COLUMN created_at
        ");

        // Step 5: Restore composite primary key
        $this->addSql("ALTER TABLE user_account ADD PRIMARY KEY (user_id, account_id)");
    }
}
