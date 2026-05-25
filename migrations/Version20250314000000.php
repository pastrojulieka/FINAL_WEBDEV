<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add email verification fields to user table.
 */
final class Version20250314000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add verification_token and is_verified to user table for email verification';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD verification_token VARCHAR(100) DEFAULT NULL, ADD is_verified TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP verification_token, DROP is_verified');
    }
}
