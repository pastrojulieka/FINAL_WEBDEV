<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fix Order table schema - drop old columns and ensure new ones have proper defaults
 */
final class Version20260528120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix Order table schema - drop old total_price column and ensure proper defaults';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();

        if ($sm->tablesExist(['order'])) {
            $table = $sm->introspectTable('order');

            // Drop old total_price column if it exists
            if ($table->hasColumn('total_price')) {
                $this->addSql('ALTER TABLE `order` DROP COLUMN total_price');
            }

            // Drop old status column if it exists
            if ($table->hasColumn('status')) {
                $this->addSql('ALTER TABLE `order` DROP COLUMN status');
            }

            // Drop old order_date column if it exists
            if ($table->hasColumn('order_date')) {
                $this->addSql('ALTER TABLE `order` DROP COLUMN order_date');
            }

            // Ensure delivery_date allows NULL as per the entity definition
            if ($table->hasColumn('delivery_date')) {
                $this->addSql('ALTER TABLE `order` MODIFY COLUMN delivery_date DATE DEFAULT NULL COMMENT "(DC2Type:date_immutable)"');
            }
        }
    }

    public function down(Schema $schema): void
    {
        // Downgrade is complex, so we skip it
    }
}
