<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260702134512 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Refactor scrape_source: category FK, product_url_selector, next_page_selector, rules -> mappings';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE scrape_source RENAME COLUMN rules TO mappings');
        $this->addSql('ALTER TABLE scrape_source ADD category_id INT NOT NULL');
        $this->addSql('ALTER TABLE scrape_source ADD product_url_selector VARCHAR(1024) NOT NULL');
        $this->addSql('ALTER TABLE scrape_source ADD next_page_selector VARCHAR(1024) DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_9C9046C812469DE2 ON scrape_source (category_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              scrape_source
            ADD
              CONSTRAINT FK_9C9046C812469DE2 FOREIGN KEY (category_id) REFERENCES category (id) NOT DEFERRABLE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE scrape_source DROP CONSTRAINT FK_9C9046C812469DE2');
        $this->addSql('DROP INDEX IDX_9C9046C812469DE2');
        $this->addSql('ALTER TABLE scrape_source DROP category_id');
        $this->addSql('ALTER TABLE scrape_source DROP product_url_selector');
        $this->addSql('ALTER TABLE scrape_source DROP next_page_selector');
        $this->addSql('ALTER TABLE scrape_source RENAME COLUMN mappings TO rules');
    }
}
