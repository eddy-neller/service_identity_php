<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260814120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop the Customer, Cart and legacy Order tables: both contexts now live in service_shop. '
            . 'Rolling back restores the structure, never the rows.';
    }

    public function up(Schema $schema): void
    {
        // Les contextes Customer et Ordering ont ete rapatries dans service_shop, ou ils vivent
        // sur MongoDB : les adresses sont embarquees dans le document client, et le plafond de 5
        // comme l'unicite du defaut sont tenus en memoire dans une ecriture atomique — la ou
        // `uniq_shop_address_default_per_customer` et un verrou pessimiste les tenaient ici.
        //
        // shop_order / shop_order_details sont l'ilot Stripe legacy : jamais rebranche, sans
        // aucun cas d'usage, et supprime avec le reste plutot que laisse orphelin.
        //
        // Ordre impose par les cles etrangeres, les dependantes d'abord.
        $this->addSql('DROP TABLE shop_cart_line');
        $this->addSql('DROP TABLE shop_cart');
        $this->addSql('DROP TABLE shop_address');
        $this->addSql('DROP TABLE shop_customer');
        $this->addSql('DROP TABLE shop_order_details');
        $this->addSql('DROP TABLE shop_order');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE shop_customer (
                id UUID NOT NULL,
                user_account_id UUID NOT NULL,
                status INT NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX ShopCustomerUserAccountUniq ON shop_customer (user_account_id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE shop_address (
                id UUID NOT NULL,
                customer_id UUID NOT NULL,
                name VARCHAR(255) NOT NULL,
                firstname VARCHAR(255) NOT NULL,
                lastname VARCHAR(255) NOT NULL,
                company VARCHAR(255) DEFAULT NULL,
                address VARCHAR(255) NOT NULL,
                zip VARCHAR(255) NOT NULL,
                city VARCHAR(255) NOT NULL,
                country VARCHAR(255) NOT NULL,
                phone VARCHAR(255) NOT NULL,
                is_default BOOLEAN DEFAULT false NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
            SQL);
        $this->addSql('CREATE INDEX ShopAddressCustomerIdx ON shop_address (customer_id)');
        $this->addSql('CREATE INDEX ShopAddressNameIdx ON shop_address (name)');
        $this->addSql('CREATE INDEX ShopAddressCityIdx ON shop_address (city)');
        $this->addSql('CREATE INDEX ShopAddressCountryIdx ON shop_address (country)');
        $this->addSql('CREATE INDEX ShopAddressIsDefaultIdx ON shop_address (customer_id, is_default)');
        $this->addSql('CREATE UNIQUE INDEX uniq_shop_address_default_per_customer ON shop_address (customer_id) WHERE is_default = true');
        $this->addSql('ALTER TABLE shop_address ADD CONSTRAINT FK_E7D2FAB9395C3F3 FOREIGN KEY (customer_id) REFERENCES shop_customer (id) ON DELETE CASCADE');

        $this->addSql(<<<'SQL'
            CREATE TABLE shop_cart (
                id UUID NOT NULL,
                customer_id UUID NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX ShopCartCustomerUniq ON shop_cart (customer_id)');
        $this->addSql('ALTER TABLE shop_cart ADD CONSTRAINT fk_shop_cart_customer FOREIGN KEY (customer_id) REFERENCES shop_customer (id) ON DELETE CASCADE');

        $this->addSql(<<<'SQL'
            CREATE TABLE shop_cart_line (
                id UUID NOT NULL,
                cart_id UUID NOT NULL,
                product_id UUID NOT NULL,
                quantity SMALLINT NOT NULL,
                PRIMARY KEY(id),
                CONSTRAINT chk_shop_cart_line_quantity CHECK (quantity >= 1 AND quantity <= 99)
            )
            SQL);
        $this->addSql('CREATE INDEX ShopCartLineCartIdx ON shop_cart_line (cart_id)');
        $this->addSql('CREATE INDEX ShopCartLineProductIdx ON shop_cart_line (product_id)');
        $this->addSql('CREATE UNIQUE INDEX ShopCartLineProductUniq ON shop_cart_line (cart_id, product_id)');
        $this->addSql('ALTER TABLE shop_cart_line ADD CONSTRAINT fk_shop_cart_line_cart FOREIGN KEY (cart_id) REFERENCES shop_cart (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shop_cart_line ADD CONSTRAINT fk_shop_cart_line_product FOREIGN KEY (product_id) REFERENCES shop_product (id) ON DELETE CASCADE');

        $this->addSql(<<<'SQL'
            CREATE TABLE shop_order (
                id UUID NOT NULL,
                user_id UUID NOT NULL,
                reference VARCHAR(255) NOT NULL,
                carrier_name VARCHAR(255) NOT NULL,
                carrier_price DOUBLE PRECISION NOT NULL,
                delivery TEXT NOT NULL,
                is_paid BOOLEAN DEFAULT false NOT NULL,
                stripe_session_id VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
            SQL);
        $this->addSql('CREATE INDEX ShopOrderUserIdx ON shop_order (user_id)');
        $this->addSql('CREATE INDEX ShopOrderIsPaidIdx ON shop_order (is_paid)');
        $this->addSql('CREATE INDEX ShopOrderStripeSessionIdx ON shop_order (stripe_session_id)');
        $this->addSql('CREATE UNIQUE INDEX ShopOrderReferenceUniq ON shop_order (reference)');
        $this->addSql('ALTER TABLE shop_order ADD CONSTRAINT FK_323FC9CAA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE shop_order_details (
                id UUID NOT NULL,
                order_id UUID DEFAULT NULL,
                product VARCHAR(255) NOT NULL,
                quantity INT NOT NULL,
                price DOUBLE PRECISION NOT NULL,
                total DOUBLE PRECISION NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
            SQL);
        $this->addSql('CREATE INDEX ShopOrderDetailsOrderIdx ON shop_order_details (order_id)');
        $this->addSql('CREATE INDEX ShopOrderDetailsProductIdx ON shop_order_details (product)');
        $this->addSql('ALTER TABLE shop_order_details ADD CONSTRAINT FK_9A4035CE8D9F6D38 FOREIGN KEY (order_id) REFERENCES shop_order (id) ON DELETE CASCADE');
    }
}
