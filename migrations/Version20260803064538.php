<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260803064538 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Append-only project image versions + product placement history; the partial unique index allows only one processing placement per project.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE project_image_version (id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, image_path VARCHAR(255) NOT NULL, project_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_FE9D1319166D1F9C ON project_image_version (project_id)');
        $this->addSql('ALTER TABLE project_image_version ADD CONSTRAINT FK_FE9D1319166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE');

        $this->addSql('CREATE TABLE product_placement (id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(16) NOT NULL, prompt TEXT NOT NULL, model VARCHAR(64) NOT NULL, context_id UUID DEFAULT NULL, product_id UUID DEFAULT NULL, result_version_id UUID DEFAULT NULL, project_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_267BC3836B00C1CF ON product_placement (context_id)');
        $this->addSql('CREATE INDEX IDX_267BC3834584665A ON product_placement (product_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_267BC383B3E72DDA ON product_placement (result_version_id)');
        $this->addSql('CREATE INDEX IDX_267BC383166D1F9C ON product_placement (project_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_active_placement_per_project ON product_placement (project_id) WHERE ((status)::text = \'processing\'::text)');
        $this->addSql('ALTER TABLE product_placement ADD CONSTRAINT FK_267BC3836B00C1CF FOREIGN KEY (context_id) REFERENCES project_context (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE product_placement ADD CONSTRAINT FK_267BC3834584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE product_placement ADD CONSTRAINT FK_267BC383B3E72DDA FOREIGN KEY (result_version_id) REFERENCES project_image_version (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE product_placement ADD CONSTRAINT FK_267BC383166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE');

        $this->addSql('INSERT INTO project_image_version (id, project_id, image_path, created_at) SELECT uuidv7(), p.id, p.image_path, p.created_at FROM project p');
        $this->addSql('ALTER TABLE project DROP image_path');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project ADD image_path VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE project SET image_path = (SELECT v.image_path FROM project_image_version v WHERE v.project_id = project.id ORDER BY v.id DESC LIMIT 1)');
        $this->addSql('UPDATE project SET image_path = \'\' WHERE image_path IS NULL');
        $this->addSql('ALTER TABLE project ALTER image_path SET NOT NULL');
        $this->addSql('ALTER TABLE product_placement DROP CONSTRAINT FK_267BC3836B00C1CF');
        $this->addSql('ALTER TABLE product_placement DROP CONSTRAINT FK_267BC3834584665A');
        $this->addSql('ALTER TABLE product_placement DROP CONSTRAINT FK_267BC383B3E72DDA');
        $this->addSql('ALTER TABLE product_placement DROP CONSTRAINT FK_267BC383166D1F9C');
        $this->addSql('ALTER TABLE project_image_version DROP CONSTRAINT FK_FE9D1319166D1F9C');
        $this->addSql('DROP TABLE product_placement');
        $this->addSql('DROP TABLE project_image_version');
    }
}
