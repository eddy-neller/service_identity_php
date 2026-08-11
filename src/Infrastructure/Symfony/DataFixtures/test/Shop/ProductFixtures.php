<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\DataFixtures\test\Shop;

use App\Infrastructure\Persistence\Doctrine\Shop\Catalog\CategoryEntity as Category;
use App\Infrastructure\Persistence\Doctrine\Shop\Catalog\ProductEntity as Product;
use App\Infrastructure\Symfony\DataFixtures\DataFixturesTrait;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Ramsey\Uuid\Uuid;
use Symfony\Component\String\Slugger\AsciiSlugger;

class ProductFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    use DataFixturesTrait;

    private const int GENERATED_PRODUCTS_COUNT = 100;

    private AsciiSlugger $slugger;

    public function __construct()
    {
        $this->slugger = new AsciiSlugger();
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create();

        $seedProducts = [
            [
                'title' => 'Product title 1',
                'subtitle' => 'Amazing product for everyone',
                'description' => 'This is a detailed description of our first product with all its features and benefits.',
                'price' => 2999,
                'categoryRef' => 'shop_category_level_1_1',
            ],
            [
                'title' => 'Product title 2',
                'subtitle' => 'Premium quality product',
                'description' => 'High-end product with exceptional quality and durability for professional use.',
                'price' => 4999,
                'categoryRef' => 'shop_category_level_0_1',
            ],
            [
                'title' => 'Product title 3',
                'subtitle' => 'Budget-friendly option',
                'description' => 'Affordable product without compromising on quality and performance.',
                'price' => 1999,
                'categoryRef' => 'shop_category_level_2_1',
            ],
            [
                'title' => 'Product title 4',
                'subtitle' => 'Innovative design',
                'description' => 'Revolutionary product with cutting-edge technology and modern aesthetics.',
                'price' => 7999,
                'categoryRef' => 'shop_category_level_1_2',
            ],
            [
                'title' => 'Product title 5',
                'subtitle' => 'Eco-friendly choice',
                'description' => 'Sustainable product made from environmentally friendly materials.',
                'price' => 3999,
                'categoryRef' => 'shop_category_level_0_2',
            ],
            [
                'title' => 'Product title 6',
                'subtitle' => 'Versatile and practical',
                'description' => 'Multi-purpose product suitable for various applications and environments.',
                'price' => 5999,
                'categoryRef' => 'shop_category_level_2_2',
            ],
            [
                'title' => 'Product title 7',
                'subtitle' => 'Compact and portable',
                'description' => 'Lightweight design perfect for travel and on-the-go use.',
                'price' => 3499,
                'categoryRef' => 'shop_category_level_1_3',
            ],
            [
                'title' => 'Product title 8',
                'subtitle' => 'Professional grade',
                'description' => 'Industry-standard product trusted by professionals worldwide.',
                'price' => 9999,
                'categoryRef' => 'shop_category_level_0_3',
            ],
            [
                'title' => 'Product title 9',
                'subtitle' => 'Limited edition',
                'description' => 'Exclusive product with unique features and premium packaging.',
                'price' => 14999,
                'categoryRef' => 'shop_category_level_2_3',
            ],
            [
                'title' => 'Product title 10',
                'subtitle' => 'Best seller',
                'description' => 'Our most popular product loved by customers for its reliability and value.',
                'price' => 4499,
                'categoryRef' => 'shop_category_level_1_4',
            ],
        ];

        $productsData = $seedProducts;

        for ($i = 1; $i <= self::GENERATED_PRODUCTS_COUNT; ++$i) {
            $productsData[] = [
                'title' => 'Product generated title ' . $i,
                'subtitle' => $faker->sentence($faker->numberBetween(3, 6)),
                'description' => $faker->realText($faker->numberBetween(120, 260)),
                'price' => $faker->numberBetween(500, 15000),
            ];
        }

        $categoryRefs = $this->buildCategoryReferences();

        foreach ($productsData as $index => $productData) {
            $product = new Product();
            $product->setId(Uuid::uuid4());

            $title = $productData['title'];
            $product->setTitle($title);
            $product->setSlug($this->generateSlug($title));

            $product->setSubtitle($productData['subtitle']);
            $product->setDescription($productData['description']);
            $product->setPrice($productData['price']);

            // Simuler qu'il y a une image
            $product->setImageName('product.jpg');

            // Répartition déterministe sur tous les niveaux pour stabiliser les tests API.
            $categoryRef = $productData['categoryRef'] ?? $categoryRefs[$index % count($categoryRefs)];
            $category = $this->getReference($categoryRef, Category::class);
            $product->setCategory($category);

            $timestamps = $this->generateTimestamps();
            $createdAt = $timestamps['createdAt'];
            $product->setCreatedAt($createdAt);
            $product->setUpdatedAt($timestamps['updatedAt']);

            $this->addReference('shop_product_' . ($index + 1), $product);

            $manager->persist($product);
        }

        $manager->flush();

        $this->updateStats($manager);
    }

    public function getDependencies(): array
    {
        return [
            CategoryFixtures::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['test'];
    }

    private function updateStats(ObjectManager $manager): void
    {
        $categories = $manager->getRepository(Category::class)->findAll();

        foreach ($categories as $category) {
            $nbProductFound = (int) $manager->getRepository(Product::class)->countNbProductByCategory($category->getId()->toString());

            $category->setNbProduct($nbProductFound);
        }

        $manager->flush();
    }

    private function generateSlug(string $title): string
    {
        return $this->slugger->slug($title)->lower()->toString();
    }

    private function buildCategoryReferences(): array
    {
        $references = [];

        for ($level = 0; $level <= 2; ++$level) {
            $count = constant(CategoryFixtures::class . '::NB_LEVEL_' . $level);

            for ($i = 1; $i <= $count; ++$i) {
                $references[] = 'shop_category_level_' . $level . '_' . $i;
            }
        }

        return $references;
    }
}
