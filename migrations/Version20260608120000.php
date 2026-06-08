<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260608120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add persistent customer carts and cart lines';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE shop_cart (id UUID NOT NULL, customer_id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');

        $this->addSql('CREATE UNIQUE INDEX ShopCartCustomerUniq ON shop_cart (customer_id)');

        $this->addSql('ALTER TABLE shop_cart ADD CONSTRAINT FK_SHOP_CART_CUSTOMER FOREIGN KEY (customer_id) REFERENCES shop_customer (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE shop_cart_line (id UUID NOT NULL, cart_id UUID NOT NULL, product_id UUID NOT NULL, quantity SMALLINT NOT NULL, PRIMARY KEY(id))');

        $this->addSql('CREATE UNIQUE INDEX ShopCartLineProductUniq ON shop_cart_line (cart_id, product_id)');
        $this->addSql('CREATE INDEX ShopCartLineCartIdx ON shop_cart_line (cart_id)');
        $this->addSql('CREATE INDEX ShopCartLineProductIdx ON shop_cart_line (product_id)');

        $this->addSql('ALTER TABLE shop_cart_line ADD CONSTRAINT FK_SHOP_CART_LINE_CART FOREIGN KEY (cart_id) REFERENCES shop_cart (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE shop_cart_line ADD CONSTRAINT FK_SHOP_CART_LINE_PRODUCT FOREIGN KEY (product_id) REFERENCES shop_product (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE shop_cart_line ADD CONSTRAINT CHK_SHOP_CART_LINE_QUANTITY CHECK (quantity BETWEEN 1 AND 99)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE shop_cart_line');
        $this->addSql('DROP TABLE shop_cart');
    }
}
