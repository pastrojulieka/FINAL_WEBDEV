<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backfill empty order status values to pending';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();

        if ($sm->tablesExist(['order']) && $sm->introspectTable('order')->hasColumn('status')) {
            $this->addSql("UPDATE `order` SET status = 'pending' WHERE status IS NULL OR status = ''");
        }
    }

    public function down(Schema $schema): void
    {
    }
}
