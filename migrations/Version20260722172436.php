<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260722172436 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE project (id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, image_path VARCHAR(255) NOT NULL, prompt TEXT NOT NULL, status VARCHAR(16) NOT NULL, user_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_2FB3D0EEA76ED395 ON project (user_id)');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EEA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project DROP CONSTRAINT FK_2FB3D0EEA76ED395');
        $this->addSql('DROP TABLE project');
    }
}
