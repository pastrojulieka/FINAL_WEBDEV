<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260523000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create booking table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $table = $schema->createTable('booking');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('customer_name', 'string', ['length' => 255]);
        $table->addColumn('service_type', 'string', ['length' => 255]);
        $table->addColumn('description', 'text');
        $table->addColumn('booking_date', 'datetime');
        $table->addColumn('start_time', 'datetime');
        $table->addColumn('end_time', 'datetime');
        $table->addColumn('status', 'string', ['length' => 20, 'default' => 'pending']);
        $table->addColumn('price', 'float', ['notnull' => false]);
        $table->addColumn('notes', 'text', ['notnull' => false]);
        $table->addColumn('created_at', 'datetime');
        $table->addColumn('updated_at', 'datetime', ['notnull' => false]);
        $table->addColumn('created_by_id', 'integer', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['created_by_id'], 'IDX_64AC3D6FB03A8386');
        $table->addForeignKeyConstraint('user', ['created_by_id'], ['id'], [], 'FK_64AC3D6FB03A8386');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $schema->dropTable('booking');
    }
}
