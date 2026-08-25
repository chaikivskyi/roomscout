<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260823202552 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Composite index for latest-image-version lookups; drop the context_id index made redundant by uniq_context_product_match.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_project_image_version_project_id_id ON project_image_version (project_id, id)');
        $this->addSql('DROP INDEX IDX_FE9D1319166D1F9C');
        $this->addSql('DROP INDEX IDX_F96295F6B00C1CF');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE INDEX IDX_F96295F6B00C1CF ON project_product_match (context_id)');
        $this->addSql('CREATE INDEX IDX_FE9D1319166D1F9C ON project_image_version (project_id)');
        $this->addSql('DROP INDEX idx_project_image_version_project_id_id');
    }
}
