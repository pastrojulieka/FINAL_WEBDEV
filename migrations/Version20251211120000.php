<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251211120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add quantity column to product for stock tracking';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if ($sm->tablesExist(['product'])) {
            $table = $sm->introspectTable('product');
            if (!$table->hasColumn('quantity')) {
                $this->addSql('ALTER TABLE product ADD quantity INT NOT NULL DEFAULT 0');
            }
        }
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if ($sm->tablesExist(['product'])) {
            $table = $sm->introspectTable('product');
            if ($table->hasColumn('quantity')) {
                $this->addSql('ALTER TABLE product DROP quantity');
            }
        }
    }
}

