<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260723131724 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Catalog: content hash of the current product thumbnail';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ADD thumbnail_hash VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP thumbnail_hash');
    }
}
