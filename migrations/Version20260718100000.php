<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260718100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add hashed application-owned refresh tokens.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_refresh_token (id UUID NOT NULL, user_id UUID NOT NULL, token_hash VARCHAR(64) NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX UserRefreshTokenUserIdIdx ON user_refresh_token (user_id)');
        $this->addSql('CREATE UNIQUE INDEX UserRefreshTokenHashUniq ON user_refresh_token (token_hash)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_refresh_token');
    }
}
