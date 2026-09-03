<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903062144 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add an optional icon image path to categories.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category ADD icon_url VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category DROP icon_url');
    }
}
