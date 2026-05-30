<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add status column to order table (pending, complete, cancelled)';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();

        if ($sm->tablesExist(['order']) && !$sm->introspectTable('order')->hasColumn('status')) {
            $this->addSql("ALTER TABLE `order` ADD status VARCHAR(20) NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();

        if ($sm->tablesExist(['order']) && $sm->introspectTable('order')->hasColumn('status')) {
            $this->addSql('ALTER TABLE `order` DROP status');
        }
    }
}
