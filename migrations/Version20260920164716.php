<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * CraueConfigBundle deliberately offers no way to create settings at runtime, so
 * every setting is defined here. Values written by a migration bypass the bundle's
 * cache, so clear the craue_config_cache pool after running this against an
 * environment where that pool is not the null adapter.
 */
final class Version20260920164716 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the runtime app settings table and seed the free search count.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE craue_config_setting (section VARCHAR(255) DEFAULT NULL, name VARCHAR(255) NOT NULL, value VARCHAR(255) DEFAULT NULL, PRIMARY KEY (name))');
        $this->addSql("INSERT INTO craue_config_setting (name, value, section) VALUES ('free_search_count', '3', 'search')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE craue_config_setting');
    }
}
