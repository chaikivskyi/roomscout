<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260724165750 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Catalog: category tree — self-referencing parent_category_id with stored level and path title';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE category
                ADD parent_category_id INT DEFAULT NULL,
                ADD level INT DEFAULT 1 NOT NULL,
                ADD path_title TEXT DEFAULT '' NOT NULL,
                ADD CONSTRAINT FK_64C19C1796A8F92 FOREIGN KEY (parent_category_id) REFERENCES category (id) NOT DEFERRABLE
            SQL);
        $this->addSql('CREATE INDEX IDX_64C19C1796A8F92 ON category (parent_category_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE category
                DROP CONSTRAINT FK_64C19C1796A8F92,
                DROP parent_category_id,
                DROP level,
                DROP path_title
            SQL);
    }
}
