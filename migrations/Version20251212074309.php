<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251212074309 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if ($sm->tablesExist(['product'])) {
            $table = $sm->introspectTable('product');
            if (!$table->hasColumn('quantity')) {
                $this->addSql('ALTER TABLE product ADD COLUMN quantity INTEGER NOT NULL');
            }
        }
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if ($sm->tablesExist(['product'])) {
            $table = $sm->introspectTable('product');
            if ($table->hasColumn('quantity')) {
                // Attempt to remove the column safely by recreating the table without it (if supported)
                $this->addSql('CREATE TEMPORARY TABLE __temp__product AS SELECT id, created_by_id, name, description, price, image, material, color FROM product');
                $this->addSql('DROP TABLE product');
                $this->addSql('CREATE TABLE product (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created_by_id INTEGER DEFAULT NULL, name VARCHAR(100) NOT NULL, description CLOB NOT NULL, price DOUBLE PRECISION NOT NULL, image VARCHAR(255) NOT NULL, material VARCHAR(255) NOT NULL, color VARCHAR(255) NOT NULL, CONSTRAINT FK_D34A04ADB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
                $this->addSql('INSERT INTO product (id, created_by_id, name, description, price, image, material, color) SELECT id, created_by_id, name, description, price, image, material, color FROM __temp__product');
                $this->addSql('DROP TABLE __temp__product');
                $this->addSql('CREATE INDEX IDX_D34A04ADB03A8386 ON product (created_by_id)');
            }
        }
    }
}
