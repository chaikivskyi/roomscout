<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260726132119 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'CatalogSearch: re-key project_product_match from project to project_context';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project_product_match DROP CONSTRAINT FK_F96295F166D1F9C');
        $this->addSql('DROP INDEX IDX_F96295F166D1F9C');
        $this->addSql('DROP INDEX uniq_project_product_match');
        $this->addSql('ALTER TABLE project_product_match DROP project_id');
        $this->addSql('ALTER TABLE project_product_match ADD context_id UUID NOT NULL');
        $this->addSql('CREATE INDEX IDX_F96295F6B00C1CF ON project_product_match (context_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_context_product_match ON project_product_match (context_id, product_id)');
        $this->addSql('ALTER TABLE project_product_match ADD CONSTRAINT FK_F96295F6B00C1CF FOREIGN KEY (context_id) REFERENCES project_context (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project_product_match DROP CONSTRAINT FK_F96295F6B00C1CF');
        $this->addSql('DROP INDEX IDX_F96295F6B00C1CF');
        $this->addSql('DROP INDEX uniq_context_product_match');
        $this->addSql('ALTER TABLE project_product_match DROP context_id');
        $this->addSql('ALTER TABLE project_product_match ADD project_id UUID NOT NULL');
        $this->addSql('CREATE INDEX IDX_F96295F166D1F9C ON project_product_match (project_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_project_product_match ON project_product_match (project_id, product_id)');
        $this->addSql('ALTER TABLE project_product_match ADD CONSTRAINT FK_F96295F166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE');
    }
}
