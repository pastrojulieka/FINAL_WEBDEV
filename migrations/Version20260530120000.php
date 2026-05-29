<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add fcm_token column to user table for mobile push notifications';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();

        if ($sm->tablesExist(['user']) && !$sm->introspectTable('user')->hasColumn('fcm_token')) {
            $this->addSql('ALTER TABLE user ADD fcm_token VARCHAR(512) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();

        if ($sm->tablesExist(['user']) && $sm->introspectTable('user')->hasColumn('fcm_token')) {
            $this->addSql('ALTER TABLE user DROP fcm_token');
        }
    }
}
