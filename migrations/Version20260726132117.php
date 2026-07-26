<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260726132117 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Project: create project_context';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE project_context (id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(16) NOT NULL, prompt TEXT NOT NULL, project_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_7443891B166D1F9C ON project_context (project_id)');
        $this->addSql('ALTER TABLE project_context ADD CONSTRAINT FK_7443891B166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE project_context');
    }
}
