<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260920180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow credential-less guest users and record when a user was created. '
            .'WARNING: down() deletes every guest user (and, via cascade, their projects, '
            .'contexts and visualizations) and does not clean up their uploaded files.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ALTER email DROP NOT NULL');
        $this->addSql('ALTER TABLE users ALTER password DROP NOT NULL');
        $this->addSql('ALTER TABLE users ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('UPDATE users SET created_at = NOW()');
        $this->addSql('ALTER TABLE users ALTER created_at SET NOT NULL');
        $this->addSql('CREATE INDEX idx_users_guest_created_at ON users (created_at) WHERE email IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_users_guest_created_at');
        $this->addSql('DELETE FROM users WHERE email IS NULL');
        $this->addSql('ALTER TABLE users DROP created_at');
        $this->addSql('ALTER TABLE users ALTER password SET NOT NULL');
        $this->addSql('ALTER TABLE users ALTER email SET NOT NULL');
    }
}
