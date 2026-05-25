<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251211134000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove order_link_id column from stock table';
    }

    public function up(Schema $schema): void
    {
        // Drop FK then column
        $this->addSql('ALTER TABLE stock DROP FOREIGN KEY FK_4B365660E9A695A7');
        $this->addSql('ALTER TABLE stock DROP order_link_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stock ADD order_link_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE stock ADD CONSTRAINT FK_4B365660E9A695A7 FOREIGN KEY (order_link_id) REFERENCES `order` (id)');
        $this->addSql('CREATE INDEX IDX_4B365660E9A695A7 ON stock (order_link_id)');
    }
}

