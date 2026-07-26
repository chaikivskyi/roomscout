<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260726132118 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'CatalogSearch: re-key project_embedding to project_context (project_context_embedding)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project_embedding DROP CONSTRAINT FK_59671079166D1F9C');
        $this->addSql('DROP INDEX UNIQ_59671079166D1F9C');
        $this->addSql('ALTER TABLE project_embedding DROP project_id');
        $this->addSql('ALTER TABLE project_embedding ADD context_id UUID NOT NULL');
        $this->addSql('ALTER TABLE project_embedding RENAME TO project_context_embedding');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_AF4A098E6B00C1CF ON project_context_embedding (context_id)');
        $this->addSql('ALTER TABLE project_context_embedding ADD CONSTRAINT FK_AF4A098E6B00C1CF FOREIGN KEY (context_id) REFERENCES project_context (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project_context_embedding DROP CONSTRAINT FK_AF4A098E6B00C1CF');
        $this->addSql('DROP INDEX UNIQ_AF4A098E6B00C1CF');
        $this->addSql('ALTER TABLE project_context_embedding DROP context_id');
        $this->addSql('ALTER TABLE project_context_embedding ADD project_id UUID NOT NULL');
        $this->addSql('ALTER TABLE project_context_embedding RENAME TO project_embedding');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_59671079166D1F9C ON project_embedding (project_id)');
        $this->addSql('ALTER TABLE project_embedding ADD CONSTRAINT FK_59671079166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE');
    }
}
