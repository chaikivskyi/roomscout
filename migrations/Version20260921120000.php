<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track when a user was last active, and index it for the guest purge.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD last_active_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('UPDATE users SET last_active_at = created_at');
        $this->addSql('ALTER TABLE users ALTER last_active_at SET NOT NULL');
        $this->addSql('DROP INDEX idx_users_guest_created_at');
        $this->addSql('CREATE INDEX idx_users_guest_last_active_at ON users (last_active_at) WHERE email IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_users_guest_last_active_at');
        $this->addSql('CREATE INDEX idx_users_guest_created_at ON users (created_at) WHERE email IS NULL');
        $this->addSql('ALTER TABLE users DROP last_active_at');
    }
}
