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
        // Intentionally left empty: skip creating the `booking` table in this environment.
    }

    public function down(Schema $schema): void
    {
        // No-op down: nothing to revert because table was not created here.
    }
}
