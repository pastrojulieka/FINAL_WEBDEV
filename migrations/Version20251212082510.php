<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251212082510 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, modified to avoid duplicate column errors
        $sm = $this->connection->createSchemaManager();
        if ($sm->tablesExist(['product'])) {
            $table = $sm->introspectTable('product');
            if (!$table->hasColumn('quantity')) {
                $this->addSql('ALTER TABLE product ADD quantity INT NOT NULL');
            }
        } else {
            // fallback: attempt to add column (table may be created in same batch)
            $this->addSql('ALTER TABLE product ADD quantity INT NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE product DROP quantity');
    }
}
