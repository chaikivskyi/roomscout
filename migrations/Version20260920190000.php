<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260920190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed the free-tier image upload size limit.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO craue_config_setting (name, value, section) VALUES ('free_max_image_size_mb', '10', 'guest') ON CONFLICT (name) DO NOTHING");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM craue_config_setting WHERE name = 'free_max_image_size_mb'");
    }
}
