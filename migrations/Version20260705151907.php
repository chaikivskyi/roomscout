<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260705151907 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add unique index on product.external_id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX uniq_product_external_id ON product (external_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_product_external_id');
    }
}
