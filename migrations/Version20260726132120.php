<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260726132120 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Project: drop prompt and status, now owned by project_context';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project DROP prompt');
        $this->addSql('ALTER TABLE project DROP status');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project ADD prompt TEXT NOT NULL');
        $this->addSql('ALTER TABLE project ADD status VARCHAR(16) NOT NULL');
    }
}
