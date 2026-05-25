<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251211122000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create stock table linked to product and optionally order';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE stock (id INT AUTO_INCREMENT NOT NULL, product_id INT NOT NULL, order_link_id INT DEFAULT NULL, quantity INT NOT NULL, INDEX IDX_4B3656604584665A (product_id), INDEX IDX_4B365660E9A695A7 (order_link_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE stock ADD CONSTRAINT FK_4B3656604584665A FOREIGN KEY (product_id) REFERENCES product (id)');
        $this->addSql('ALTER TABLE stock ADD CONSTRAINT FK_4B365660E9A695A7 FOREIGN KEY (order_link_id) REFERENCES `order` (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stock DROP FOREIGN KEY FK_4B3656604584665A');
        $this->addSql('ALTER TABLE stock DROP FOREIGN KEY FK_4B365660E9A695A7');
        $this->addSql('DROP TABLE stock');
    }
}

