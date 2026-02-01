<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251117205507 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Shop tables';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE shop_address (id UUID NOT NULL, user_id UUID DEFAULT NULL, name VARCHAR(255) NOT NULL, firstname VARCHAR(255) NOT NULL, lastname VARCHAR(255) NOT NULL, company VARCHAR(255) DEFAULT NULL, address VARCHAR(255) NOT NULL, zip VARCHAR(255) NOT NULL, city VARCHAR(255) NOT NULL, country VARCHAR(255) NOT NULL, phone VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX ShopAddressUserIdx ON shop_address (user_id)');

        $this->addSql('CREATE TABLE shop_category (id UUID NOT NULL, parent_id UUID DEFAULT NULL, title VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, nb_product INT NOT NULL, slug VARCHAR(128) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, root VARCHAR(255) DEFAULT NULL, lvl INT NOT NULL, lft INT NOT NULL, rgt INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_DDF4E357989D9B62 ON shop_category (slug)');
        $this->addSql('CREATE INDEX ShopCategoryParentIdx ON shop_category (parent_id)');

        $this->addSql('CREATE TABLE shop_order (id UUID NOT NULL, user_id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, carrier_name VARCHAR(255) NOT NULL, carrier_price DOUBLE PRECISION NOT NULL, delivery TEXT NOT NULL, is_paid BOOLEAN DEFAULT false NOT NULL, reference VARCHAR(255) NOT NULL, stripe_session_id VARCHAR(255) DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_323FC9CAAEA34913 ON shop_order (reference)');
        $this->addSql('CREATE INDEX ShopOrderUserIdx ON shop_order (user_id)');
        $this->addSql('CREATE INDEX ShopOrderReferenceIdx ON shop_order (reference)');
        $this->addSql('CREATE INDEX ShopOrderIsPaidIdx ON shop_order (is_paid)');
        $this->addSql('CREATE INDEX ShopOrderStripeSessionIdx ON shop_order (stripe_session_id)');

        $this->addSql('CREATE TABLE shop_order_details (id UUID NOT NULL, order_id UUID DEFAULT NULL, product VARCHAR(255) NOT NULL, quantity INT NOT NULL, price DOUBLE PRECISION NOT NULL, total DOUBLE PRECISION NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX ShopOrderDetailsOrderIdx ON shop_order_details (order_id)');
        $this->addSql('CREATE INDEX ShopOrderDetailsProductIdx ON shop_order_details (product)');

        $this->addSql('CREATE TABLE shop_product (id UUID NOT NULL, category_id UUID NOT NULL, title VARCHAR(255) NOT NULL, subtitle VARCHAR(255) NOT NULL, description TEXT NOT NULL, price DOUBLE PRECISION NOT NULL, slug VARCHAR(255) NOT NULL, image_name VARCHAR(255) DEFAULT NULL, image_updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D0794487989D9B62 ON shop_product (slug)');
        $this->addSql('CREATE INDEX ShopProductCategoryIdx ON shop_product (category_id)');

        $this->addSql('ALTER TABLE shop_address ADD CONSTRAINT FK_E7D2FABA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE shop_category ADD CONSTRAINT FK_DDF4E357727ACA70 FOREIGN KEY (parent_id) REFERENCES shop_category (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE shop_order ADD CONSTRAINT FK_323FC9CAA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE shop_order_details ADD CONSTRAINT FK_9A4035CE8D9F6D38 FOREIGN KEY (order_id) REFERENCES shop_order (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE shop_product ADD CONSTRAINT FK_D079448712469DE2 FOREIGN KEY (category_id) REFERENCES shop_category (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

        $this->addSql('ALTER TABLE shop_address DROP CONSTRAINT FK_E7D2FABA76ED395');
        $this->addSql('ALTER TABLE shop_category DROP CONSTRAINT FK_DDF4E357727ACA70');
        $this->addSql('ALTER TABLE shop_order DROP CONSTRAINT FK_323FC9CAA76ED395');
        $this->addSql('ALTER TABLE shop_order_details DROP CONSTRAINT FK_9A4035CE8D9F6D38');
        $this->addSql('ALTER TABLE shop_product DROP CONSTRAINT FK_D079448712469DE2');

        $this->addSql('DROP TABLE shop_address');
        $this->addSql('DROP TABLE shop_carrier');
        $this->addSql('DROP TABLE shop_category');
        $this->addSql('DROP TABLE shop_order');
        $this->addSql('DROP TABLE shop_order_details');
        $this->addSql('DROP TABLE shop_product');
    }
}
