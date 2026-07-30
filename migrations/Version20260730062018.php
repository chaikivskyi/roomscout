<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260730062018 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migrate all entity primary keys to UUIDv7 (destructive: all rows are discarded)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category DROP CONSTRAINT FK_64C19C1796A8F92');
        $this->addSql('ALTER TABLE product DROP CONSTRAINT FK_D34A04AD12469DE2');
        $this->addSql('ALTER TABLE scrape_source DROP CONSTRAINT FK_9C9046C812469DE2');
        $this->addSql('ALTER TABLE password_reset_token DROP CONSTRAINT FK_6B7BA4B6A76ED395');
        $this->addSql('ALTER TABLE project DROP CONSTRAINT FK_2FB3D0EEA76ED395');
        $this->addSql('ALTER TABLE product_embedding DROP CONSTRAINT FK_26FBE3234584665A');
        $this->addSql('ALTER TABLE project_product_match DROP CONSTRAINT FK_F96295F4584665A');

        $this->addSql('TRUNCATE TABLE category, product, scrape_source, users, password_reset_token, project, project_context, product_embedding, project_context_embedding, project_product_match CASCADE');
        $this->addSql('DELETE FROM messenger_messages');

        $this->addSql('DROP INDEX IDX_64C19C1796A8F92');
        $this->addSql('DROP INDEX IDX_D34A04AD12469DE2');
        $this->addSql('DROP INDEX uniq_product_uuid');
        $this->addSql('DROP INDEX IDX_9C9046C812469DE2');
        $this->addSql('DROP INDEX IDX_6B7BA4B6A76ED395');
        $this->addSql('DROP INDEX IDX_2FB3D0EEA76ED395');
        $this->addSql('DROP INDEX UNIQ_26FBE3234584665A');
        $this->addSql('DROP INDEX uniq_context_product_match');
        $this->addSql('DROP INDEX IDX_F96295F4584665A');

        $this->addSql('ALTER TABLE category DROP parent_category_id');
        $this->addSql('ALTER TABLE category DROP id');
        $this->addSql('ALTER TABLE product DROP uuid');
        $this->addSql('ALTER TABLE product DROP category_id');
        $this->addSql('ALTER TABLE product DROP id');
        $this->addSql('ALTER TABLE scrape_source DROP category_id');
        $this->addSql('ALTER TABLE scrape_source DROP id');
        $this->addSql('ALTER TABLE users DROP id');
        $this->addSql('ALTER TABLE password_reset_token DROP user_id');
        $this->addSql('ALTER TABLE password_reset_token DROP id');
        $this->addSql('ALTER TABLE project DROP user_id');
        $this->addSql('ALTER TABLE product_embedding DROP product_id');
        $this->addSql('ALTER TABLE product_embedding DROP id');
        $this->addSql('ALTER TABLE project_context_embedding DROP id');
        $this->addSql('ALTER TABLE project_product_match DROP product_id');
        $this->addSql('ALTER TABLE project_product_match DROP id');

        $this->addSql('ALTER TABLE category ADD id UUID NOT NULL');
        $this->addSql('ALTER TABLE category ADD parent_category_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE category ADD PRIMARY KEY (id)');
        $this->addSql('ALTER TABLE product ADD id UUID NOT NULL');
        $this->addSql('ALTER TABLE product ADD category_id UUID NOT NULL');
        $this->addSql('ALTER TABLE product ADD PRIMARY KEY (id)');
        $this->addSql('ALTER TABLE scrape_source ADD id UUID NOT NULL');
        $this->addSql('ALTER TABLE scrape_source ADD category_id UUID NOT NULL');
        $this->addSql('ALTER TABLE scrape_source ADD PRIMARY KEY (id)');
        $this->addSql('ALTER TABLE users ADD id UUID NOT NULL');
        $this->addSql('ALTER TABLE users ADD PRIMARY KEY (id)');
        $this->addSql('ALTER TABLE password_reset_token ADD id UUID NOT NULL');
        $this->addSql('ALTER TABLE password_reset_token ADD user_id UUID NOT NULL');
        $this->addSql('ALTER TABLE password_reset_token ADD PRIMARY KEY (id)');
        $this->addSql('ALTER TABLE project ADD user_id UUID NOT NULL');
        $this->addSql('ALTER TABLE product_embedding ADD id UUID NOT NULL');
        $this->addSql('ALTER TABLE product_embedding ADD product_id UUID NOT NULL');
        $this->addSql('ALTER TABLE product_embedding ADD PRIMARY KEY (id)');
        $this->addSql('ALTER TABLE project_context_embedding ADD id UUID NOT NULL');
        $this->addSql('ALTER TABLE project_context_embedding ADD PRIMARY KEY (id)');
        $this->addSql('ALTER TABLE project_product_match ADD id UUID NOT NULL');
        $this->addSql('ALTER TABLE project_product_match ADD product_id UUID NOT NULL');
        $this->addSql('ALTER TABLE project_product_match ADD PRIMARY KEY (id)');

        $this->addSql('CREATE INDEX IDX_64C19C1796A8F92 ON category (parent_category_id)');
        $this->addSql('CREATE INDEX IDX_D34A04AD12469DE2 ON product (category_id)');
        $this->addSql('CREATE INDEX IDX_9C9046C812469DE2 ON scrape_source (category_id)');
        $this->addSql('CREATE INDEX IDX_6B7BA4B6A76ED395 ON password_reset_token (user_id)');
        $this->addSql('CREATE INDEX IDX_2FB3D0EEA76ED395 ON project (user_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_26FBE3234584665A ON product_embedding (product_id)');
        $this->addSql('CREATE INDEX IDX_F96295F4584665A ON project_product_match (product_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_context_product_match ON project_product_match (context_id, product_id)');

        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C1796A8F92 FOREIGN KEY (parent_category_id) REFERENCES category (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04AD12469DE2 FOREIGN KEY (category_id) REFERENCES category (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE scrape_source ADD CONSTRAINT FK_9C9046C812469DE2 FOREIGN KEY (category_id) REFERENCES category (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE password_reset_token ADD CONSTRAINT FK_6B7BA4B6A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EEA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE product_embedding ADD CONSTRAINT FK_26FBE3234584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE project_product_match ADD CONSTRAINT FK_F96295F4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category DROP CONSTRAINT FK_64C19C1796A8F92');
        $this->addSql('ALTER TABLE product DROP CONSTRAINT FK_D34A04AD12469DE2');
        $this->addSql('ALTER TABLE scrape_source DROP CONSTRAINT FK_9C9046C812469DE2');
        $this->addSql('ALTER TABLE password_reset_token DROP CONSTRAINT FK_6B7BA4B6A76ED395');
        $this->addSql('ALTER TABLE project DROP CONSTRAINT FK_2FB3D0EEA76ED395');
        $this->addSql('ALTER TABLE product_embedding DROP CONSTRAINT FK_26FBE3234584665A');
        $this->addSql('ALTER TABLE project_product_match DROP CONSTRAINT FK_F96295F4584665A');

        $this->addSql('TRUNCATE TABLE category, product, scrape_source, users, password_reset_token, project, project_context, product_embedding, project_context_embedding, project_product_match CASCADE');
        $this->addSql('DELETE FROM messenger_messages');

        $this->addSql('DROP INDEX IDX_64C19C1796A8F92');
        $this->addSql('DROP INDEX IDX_D34A04AD12469DE2');
        $this->addSql('DROP INDEX IDX_9C9046C812469DE2');
        $this->addSql('DROP INDEX IDX_6B7BA4B6A76ED395');
        $this->addSql('DROP INDEX IDX_2FB3D0EEA76ED395');
        $this->addSql('DROP INDEX UNIQ_26FBE3234584665A');
        $this->addSql('DROP INDEX uniq_context_product_match');
        $this->addSql('DROP INDEX IDX_F96295F4584665A');

        $this->addSql('ALTER TABLE category DROP parent_category_id');
        $this->addSql('ALTER TABLE category DROP id');
        $this->addSql('ALTER TABLE product DROP category_id');
        $this->addSql('ALTER TABLE product DROP id');
        $this->addSql('ALTER TABLE scrape_source DROP category_id');
        $this->addSql('ALTER TABLE scrape_source DROP id');
        $this->addSql('ALTER TABLE users DROP id');
        $this->addSql('ALTER TABLE password_reset_token DROP user_id');
        $this->addSql('ALTER TABLE password_reset_token DROP id');
        $this->addSql('ALTER TABLE project DROP user_id');
        $this->addSql('ALTER TABLE product_embedding DROP product_id');
        $this->addSql('ALTER TABLE product_embedding DROP id');
        $this->addSql('ALTER TABLE project_context_embedding DROP id');
        $this->addSql('ALTER TABLE project_product_match DROP product_id');
        $this->addSql('ALTER TABLE project_product_match DROP id');

        $this->addSql('ALTER TABLE category ADD id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL');
        $this->addSql('ALTER TABLE category ADD parent_category_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE category ADD PRIMARY KEY (id)');
        $this->addSql('ALTER TABLE product ADD id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL');
        $this->addSql('ALTER TABLE product ADD uuid UUID NOT NULL');
        $this->addSql('ALTER TABLE product ADD category_id INT NOT NULL');
        $this->addSql('ALTER TABLE product ADD PRIMARY KEY (id)');
        $this->addSql('ALTER TABLE scrape_source ADD id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL');
        $this->addSql('ALTER TABLE scrape_source ADD category_id INT NOT NULL');
        $this->addSql('ALTER TABLE scrape_source ADD PRIMARY KEY (id)');
        $this->addSql('ALTER TABLE users ADD id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL');
        $this->addSql('ALTER TABLE users ADD PRIMARY KEY (id)');
        $this->addSql('ALTER TABLE password_reset_token ADD id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL');
        $this->addSql('ALTER TABLE password_reset_token ADD user_id INT NOT NULL');
        $this->addSql('ALTER TABLE password_reset_token ADD PRIMARY KEY (id)');
        $this->addSql('ALTER TABLE project ADD user_id INT NOT NULL');
        $this->addSql('ALTER TABLE product_embedding ADD id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL');
        $this->addSql('ALTER TABLE product_embedding ADD product_id INT NOT NULL');
        $this->addSql('ALTER TABLE product_embedding ADD PRIMARY KEY (id)');
        $this->addSql('ALTER TABLE project_context_embedding ADD id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL');
        $this->addSql('ALTER TABLE project_context_embedding ADD PRIMARY KEY (id)');
        $this->addSql('ALTER TABLE project_product_match ADD id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL');
        $this->addSql('ALTER TABLE project_product_match ADD product_id INT NOT NULL');
        $this->addSql('ALTER TABLE project_product_match ADD PRIMARY KEY (id)');

        $this->addSql('CREATE INDEX IDX_64C19C1796A8F92 ON category (parent_category_id)');
        $this->addSql('CREATE INDEX IDX_D34A04AD12469DE2 ON product (category_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_product_uuid ON product (uuid)');
        $this->addSql('CREATE INDEX IDX_9C9046C812469DE2 ON scrape_source (category_id)');
        $this->addSql('CREATE INDEX IDX_6B7BA4B6A76ED395 ON password_reset_token (user_id)');
        $this->addSql('CREATE INDEX IDX_2FB3D0EEA76ED395 ON project (user_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_26FBE3234584665A ON product_embedding (product_id)');
        $this->addSql('CREATE INDEX IDX_F96295F4584665A ON project_product_match (product_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_context_product_match ON project_product_match (context_id, product_id)');

        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C1796A8F92 FOREIGN KEY (parent_category_id) REFERENCES category (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04AD12469DE2 FOREIGN KEY (category_id) REFERENCES category (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE scrape_source ADD CONSTRAINT FK_9C9046C812469DE2 FOREIGN KEY (category_id) REFERENCES category (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE password_reset_token ADD CONSTRAINT FK_6B7BA4B6A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EEA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE product_embedding ADD CONSTRAINT FK_26FBE3234584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE project_product_match ADD CONSTRAINT FK_F96295F4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE NOT DEFERRABLE');
    }
}
