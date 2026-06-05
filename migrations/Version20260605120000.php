<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260605120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add default address flag for shop customers';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_address ADD is_default BOOLEAN DEFAULT false NOT NULL');

        $this->addSql('UPDATE shop_address SET is_default = true WHERE id IN (SELECT DISTINCT ON (customer_id) id FROM shop_address ORDER BY customer_id, created_at ASC, id ASC)');

        $this->addSql('CREATE INDEX ShopAddressIsDefaultIdx ON shop_address (customer_id, is_default)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_SHOP_ADDRESS_DEFAULT_PER_CUSTOMER ON shop_address (customer_id) WHERE is_default = true');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_SHOP_ADDRESS_DEFAULT_PER_CUSTOMER');
        $this->addSql('DROP INDEX ShopAddressIsDefaultIdx');

        $this->addSql('ALTER TABLE shop_address DROP is_default');
    }
}
