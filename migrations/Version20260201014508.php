<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260201014508 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE shop_customer (id UUID NOT NULL, user_account_id UUID NOT NULL, status INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');

        $this->addSql('CREATE UNIQUE INDEX UNIQ_5A81749F3C0C9956 ON shop_customer (user_account_id)');
        $this->addSql('CREATE INDEX ShopCustomerUserAccountIdx ON shop_customer (user_account_id)');

        $this->addSql('ALTER TABLE shop_address DROP CONSTRAINT fk_e7d2faba76ed395');
        $this->addSql('DROP INDEX shopaddressuseridx');

        $this->addSql('ALTER TABLE shop_address ADD customer_id UUID NOT NULL');
        $this->addSql('ALTER TABLE shop_address DROP user_id');

        $this->addSql('ALTER TABLE shop_address ADD CONSTRAINT FK_E7D2FAB9395C3F3 FOREIGN KEY (customer_id) REFERENCES shop_customer (id) ON DELETE CASCADE NOT DEFERRABLE');

        $this->addSql('CREATE INDEX ShopAddressCustomerIdx ON shop_address (customer_id)');
        $this->addSql('CREATE INDEX ShopAddressNameIdx ON shop_address (name)');
        $this->addSql('CREATE INDEX ShopAddressCityIdx ON shop_address (city)');
        $this->addSql('CREATE INDEX ShopAddressCountryIdx ON shop_address (country)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE shop_customer');

        $this->addSql('DROP INDEX ShopAddressCustomerIdx');
        $this->addSql('DROP INDEX ShopAddressNameIdx');
        $this->addSql('DROP INDEX ShopAddressCityIdx');
        $this->addSql('DROP INDEX ShopAddressCountryIdx');

        $this->addSql('ALTER TABLE shop_address DROP CONSTRAINT FK_E7D2FAB9395C3F3');

        $this->addSql('ALTER TABLE shop_address ADD user_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE shop_address DROP customer_id');

        $this->addSql('ALTER TABLE shop_address ADD CONSTRAINT fk_e7d2faba76ed395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX shopaddressuseridx ON shop_address (user_id)');
    }
}
