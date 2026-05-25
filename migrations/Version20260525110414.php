<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260525110414 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();

        // 1. activity_log
        if (!$sm->tablesExist(['activity_log'])) {
            $this->addSql('CREATE TABLE activity_log (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, user_email VARCHAR(180) NOT NULL, role VARCHAR(64) NOT NULL, action VARCHAR(32) NOT NULL, subject VARCHAR(120) DEFAULT NULL, subject_id VARCHAR(64) DEFAULT NULL, details LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_FD06F647A76ED395 (user_id), INDEX idx_activity_log_created_at (created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        // 2. booking
        if (!$sm->tablesExist(['booking'])) {
            $this->addSql('CREATE TABLE booking (id INT AUTO_INCREMENT NOT NULL, created_by_id INT DEFAULT NULL, customer_name VARCHAR(255) NOT NULL, service_type VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, booking_date DATETIME NOT NULL, start_time DATETIME NOT NULL, end_time DATETIME NOT NULL, status VARCHAR(20) NOT NULL, price DOUBLE PRECISION DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, INDEX IDX_E00CEDDEB03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        } else {
            $table = $sm->introspectTable('booking');
            if (!$table->hasColumn('created_by_id')) {
                $this->addSql('ALTER TABLE booking ADD created_by_id INT DEFAULT NULL');
                $this->addSql('CREATE INDEX IDX_E00CEDDEB03A8386 ON booking (created_by_id)');
            }
        }

        // 3. cart
        if (!$sm->tablesExist(['cart'])) {
            $this->addSql('CREATE TABLE cart (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_BA388B7A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        // 4. cart_item
        if (!$sm->tablesExist(['cart_item'])) {
            $this->addSql('CREATE TABLE cart_item (id INT AUTO_INCREMENT NOT NULL, cart_id INT NOT NULL, product_id INT NOT NULL, quantity INT NOT NULL, INDEX IDX_F0FE25271AD5CDBF (cart_id), INDEX IDX_F0FE25274584665A (product_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        // 5. category
        if (!$sm->tablesExist(['category'])) {
            $this->addSql('CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        // 6. customer
        if (!$sm->tablesExist(['customer'])) {
            $this->addSql('CREATE TABLE customer (id INT AUTO_INCREMENT NOT NULL, created_by_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, email_address VARCHAR(255) NOT NULL, phone_number VARCHAR(20) NOT NULL, address VARCHAR(255) NOT NULL, INDEX IDX_81398E09B03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        } else {
            $table = $sm->introspectTable('customer');
            if (!$table->hasColumn('created_by_id')) {
                $this->addSql('ALTER TABLE customer ADD created_by_id INT DEFAULT NULL');
                $this->addSql('CREATE INDEX IDX_81398E09B03A8386 ON customer (created_by_id)');
            }
        }

        // 7. order
        if (!$sm->tablesExist(['order'])) {
            $this->addSql('CREATE TABLE `order` (id INT AUTO_INCREMENT NOT NULL, created_by_id INT DEFAULT NULL, customer_name VARCHAR(255) NOT NULL, product_name VARCHAR(255) NOT NULL, material VARCHAR(255) NOT NULL, color VARCHAR(255) NOT NULL, quantity INT NOT NULL, price DOUBLE PRECISION NOT NULL, total_amount DOUBLE PRECISION NOT NULL, date DATETIME NOT NULL, delivery_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', INDEX IDX_F5299398B03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        } else {
            $table = $sm->introspectTable('order');
            if (!$table->hasColumn('created_by_id')) {
                $this->addSql('ALTER TABLE `order` ADD created_by_id INT DEFAULT NULL');
                $this->addSql('CREATE INDEX IDX_F5299398B03A8386 ON `order` (created_by_id)');
            }
            if (!$table->hasColumn('total_amount')) {
                // Check if total_price exists to migrate data if needed, but for now just add total_amount
                $this->addSql('ALTER TABLE `order` ADD total_amount DOUBLE PRECISION NOT NULL DEFAULT 0');
            }
            if (!$table->hasColumn('date')) {
                $this->addSql('ALTER TABLE `order` ADD date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
            }
        }

        // 8. product
        if (!$sm->tablesExist(['product'])) {
            $this->addSql('CREATE TABLE product (id INT AUTO_INCREMENT NOT NULL, created_by_id INT DEFAULT NULL, name VARCHAR(100) NOT NULL, description LONGTEXT NOT NULL, price DOUBLE PRECISION NOT NULL, image VARCHAR(255) NOT NULL, material VARCHAR(255) NOT NULL, color VARCHAR(255) NOT NULL, quantity INT NOT NULL, INDEX IDX_D34A04ADB03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        } else {
            $table = $sm->introspectTable('product');
            if (!$table->hasColumn('created_by_id')) {
                $this->addSql('ALTER TABLE product ADD created_by_id INT DEFAULT NULL');
                $this->addSql('CREATE INDEX IDX_D34A04ADB03A8386 ON product (created_by_id)');
            }
            if (!$table->hasColumn('quantity')) {
                $this->addSql('ALTER TABLE product ADD quantity INT NOT NULL DEFAULT 0');
            }
        }

        // 9. product_category
        if (!$sm->tablesExist(['product_category'])) {
            $this->addSql('CREATE TABLE product_category (product_id INT NOT NULL, category_id INT NOT NULL, INDEX IDX_CDFC73564584665A (product_id), INDEX IDX_CDFC735612469DE2 (category_id), PRIMARY KEY(product_id, category_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        // 10. stock
        if (!$sm->tablesExist(['stock'])) {
            $this->addSql('CREATE TABLE stock (id INT AUTO_INCREMENT NOT NULL, product_id INT NOT NULL, created_by_id INT DEFAULT NULL, quantity INT NOT NULL, status VARCHAR(20) NOT NULL, INDEX IDX_4B3656604584665A (product_id), INDEX IDX_4B365660B03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        } else {
            $table = $sm->introspectTable('stock');
            if (!$table->hasColumn('created_by_id')) {
                $this->addSql('ALTER TABLE stock ADD created_by_id INT DEFAULT NULL');
                $this->addSql('CREATE INDEX IDX_4B365660B03A8386 ON stock (created_by_id)');
            }
        }

        // 11. user
        if (!$sm->tablesExist(['user'])) {
            $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, verification_token VARCHAR(100) DEFAULT NULL, is_verified TINYINT(1) DEFAULT 0 NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        // 12. messenger_messages
        if (!$sm->tablesExist(['messenger_messages'])) {
            $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        // Helper to check if a foreign key exists
        $foreignKeyExists = function(string $tableName, string $constraintName) use ($sm): bool {
            if (!$sm->tablesExist([$tableName])) {
                return false;
            }
            $fks = $sm->listTableForeignKeys($tableName);
            foreach ($fks as $fk) {
                if ($fk->getName() === $constraintName) {
                    return true;
                }
            }
            return false;
        };

        // Add constraints safely
        if (!$foreignKeyExists('activity_log', 'FK_FD06F647A76ED395')) {
            $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT FK_FD06F647A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
        }
        if (!$foreignKeyExists('booking', 'FK_E00CEDDEB03A8386')) {
            $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDEB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        }
        if (!$foreignKeyExists('cart', 'FK_BA388B7A76ED395')) {
            $this->addSql('ALTER TABLE cart ADD CONSTRAINT FK_BA388B7A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        }
        if (!$foreignKeyExists('cart_item', 'FK_F0FE25271AD5CDBF')) {
            $this->addSql('ALTER TABLE cart_item ADD CONSTRAINT FK_F0FE25271AD5CDBF FOREIGN KEY (cart_id) REFERENCES cart (id)');
        }
        if (!$foreignKeyExists('cart_item', 'FK_F0FE25274584665A')) {
            $this->addSql('ALTER TABLE cart_item ADD CONSTRAINT FK_F0FE25274584665A FOREIGN KEY (product_id) REFERENCES product (id)');
        }
        if (!$foreignKeyExists('customer', 'FK_81398E09B03A8386')) {
            $this->addSql('ALTER TABLE customer ADD CONSTRAINT FK_81398E09B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        }
        if (!$foreignKeyExists('order', 'FK_F5299398B03A8386')) {
            $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F5299398B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        }
        if (!$foreignKeyExists('product', 'FK_D34A04ADB03A8386')) {
            $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04ADB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        }
        if (!$foreignKeyExists('product_category', 'FK_CDFC73564584665A')) {
            $this->addSql('ALTER TABLE product_category ADD CONSTRAINT FK_CDFC73564584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        }
        if (!$foreignKeyExists('product_category', 'FK_CDFC735612469DE2')) {
            $this->addSql('ALTER TABLE product_category ADD CONSTRAINT FK_CDFC735612469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE');
        }
        if (!$foreignKeyExists('stock', 'FK_4B3656604584665A')) {
            $this->addSql('ALTER TABLE stock ADD CONSTRAINT FK_4B3656604584665A FOREIGN KEY (product_id) REFERENCES product (id)');
        }
        if (!$foreignKeyExists('stock', 'FK_4B365660B03A8386')) {
            $this->addSql('ALTER TABLE stock ADD CONSTRAINT FK_4B365660B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activity_log DROP FOREIGN KEY FK_FD06F647A76ED395');
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDEB03A8386');
        $this->addSql('ALTER TABLE cart DROP FOREIGN KEY FK_BA388B7A76ED395');
        $this->addSql('ALTER TABLE cart_item DROP FOREIGN KEY FK_F0FE25271AD5CDBF');
        $this->addSql('ALTER TABLE cart_item DROP FOREIGN KEY FK_F0FE25274584665A');
        $this->addSql('ALTER TABLE customer DROP FOREIGN KEY FK_81398E09B03A8386');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F5299398B03A8386');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04ADB03A8386');
        $this->addSql('ALTER TABLE product_category DROP FOREIGN KEY FK_CDFC73564584665A');
        $this->addSql('ALTER TABLE product_category DROP FOREIGN KEY FK_CDFC735612469DE2');
        $this->addSql('ALTER TABLE stock DROP FOREIGN KEY FK_4B3656604584665A');
        $this->addSql('ALTER TABLE stock DROP FOREIGN KEY FK_4B365660B03A8386');
        $this->addSql('DROP TABLE activity_log');
        $this->addSql('DROP TABLE booking');
        $this->addSql('DROP TABLE cart');
        $this->addSql('DROP TABLE cart_item');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE customer');
        $this->addSql('DROP TABLE `order`');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP TABLE product_category');
        $this->addSql('DROP TABLE stock');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
