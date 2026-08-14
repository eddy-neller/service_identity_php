<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260814130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop the Catalog tables: the Shop bounded context now lives entirely in service_shop. '
            . 'Rolling back restores the structure, never the rows.';
    }

    public function up(Schema $schema): void
    {
        // Dernier morceau du contexte Shop. Le catalogue vit desormais dans service_shop, sur
        // MongoDB : `lvl`/`lft`/`rgt`/`root` — le nested set de Gedmo qui tenait l'arbre des
        // categories — n'ont plus d'equivalent la-bas, le niveau y est calcule dans `save()` et
        // propage a la descendance. C'est pourquoi `tree` passe a `false` cote Gedmo dans le
        // meme commit : plus aucune entite n'en depend.
        //
        // `pg_trgm` reste : la table `user` porte encore deux index GIN trigram.
        $this->addSql('DROP TABLE shop_product');
        $this->addSql('DROP TABLE shop_category');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE shop_category (
                id UUID NOT NULL,
                parent_id UUID DEFAULT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT DEFAULT NULL,
                nb_product INT NOT NULL,
                slug VARCHAR(128) NOT NULL,
                root VARCHAR(255) DEFAULT NULL,
                lvl INT NOT NULL,
                lft INT NOT NULL,
                rgt INT NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
            SQL);
        $this->addSql('CREATE INDEX ShopCategoryParentIdx ON shop_category (parent_id)');
        $this->addSql('CREATE INDEX ShopCategoryTitleIdx ON shop_category (title)');
        $this->addSql('CREATE INDEX ShopCategoryLevelIdx ON shop_category (lvl)');
        $this->addSql('CREATE INDEX ShopCategoryNbProductIdx ON shop_category (nb_product)');
        $this->addSql('CREATE INDEX ShopCategoryCreatedAtIdx ON shop_category (created_at)');
        $this->addSql('CREATE UNIQUE INDEX ShopCategorySlugUniq ON shop_category (slug)');
        $this->addSql('CREATE INDEX ShopCategoryTitleTrgmIdx ON shop_category USING gin (title gin_trgm_ops)');
        $this->addSql('CREATE INDEX ShopCategoryDescriptionTrgmIdx ON shop_category USING gin (description gin_trgm_ops)');
        $this->addSql('ALTER TABLE shop_category ADD CONSTRAINT FK_DDF4E357727ACA70 FOREIGN KEY (parent_id) REFERENCES shop_category (id) ON DELETE CASCADE');

        $this->addSql(<<<'SQL'
            CREATE TABLE shop_product (
                id UUID NOT NULL,
                category_id UUID NOT NULL,
                title VARCHAR(255) NOT NULL,
                subtitle VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                price DOUBLE PRECISION NOT NULL,
                slug VARCHAR(255) NOT NULL,
                image_name VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
            SQL);
        $this->addSql('CREATE INDEX ShopProductCategoryIdx ON shop_product (category_id)');
        $this->addSql('CREATE INDEX ShopProductTitleIdx ON shop_product (title)');
        $this->addSql('CREATE INDEX ShopProductPriceIdx ON shop_product (price)');
        $this->addSql('CREATE INDEX ShopProductCreatedAtIdx ON shop_product (created_at)');
        $this->addSql('CREATE UNIQUE INDEX ShopProductSlugUniq ON shop_product (slug)');
        $this->addSql('CREATE INDEX ShopProductTitleTrgmIdx ON shop_product USING gin (title gin_trgm_ops)');
        $this->addSql('CREATE INDEX ShopProductSubtitleTrgmIdx ON shop_product USING gin (subtitle gin_trgm_ops)');
        $this->addSql('CREATE INDEX ShopProductDescriptionTrgmIdx ON shop_product USING gin (description gin_trgm_ops)');
        $this->addSql('ALTER TABLE shop_product ADD CONSTRAINT FK_D079448712469DE2 FOREIGN KEY (category_id) REFERENCES shop_category (id) ON DELETE CASCADE');
    }
}
