<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\DataFixtures\dev\Shop;

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

    private AsciiSlugger $slugger;

    public function __construct()
    {
        $this->slugger = new AsciiSlugger();
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create();

        $seedProducts = [
            0 => [
                'title' => 'Bonnet rouge',
                'subtitle' => "Le bonnet parfait pour l'hiver",
                'price' => 900,
                'imageName' => 'bonnet1.jpg',
            ],
            1 => [
                'title' => 'Le Bonnet du skieur',
                'subtitle' => 'Le bonnet parfait pour le ski',
                'price' => 1200,
                'imageName' => 'bonnet2.jpg',
            ],
            2 => [
                'title' => 'L\'écharpe du lover',
                'subtitle' => 'L\'écharpe parfait pour les soirées romantiques',
                'price' => 1900,
                'imageName' => 'echarpe1.jpg',
            ],
            3 => [
                'title' => 'L\'écharpe du samedi soir',
                'subtitle' => 'L\'écharpe parfait pour vos week-ends',
                'price' => 1400,
                'imageName' => 'echarpe2.jpg',
            ],
            4 => [
                'title' => 'Le manteau de soirée',
                'subtitle' => 'Le manteau martiniquais pour vos soirées',
                'price' => 6900,
                'imageName' => 'manteau1.jpg',
            ],
            5 => [
                'title' => 'Le manteau famille',
                'subtitle' => 'Le manteau pour vos sorties en famille',
                'price' => 7990,
                'imageName' => 'manteau2.jpg',
            ],
            6 => [
                'title' => 'Le T-Shirt manche longue',
                'subtitle' => 'Le T-Shirt taillé pour les hommes',
                'price' => 1490,
                'imageName' => 'tshirt2.jpg',
            ],
            7 => [
                'title' => 'Le T-Shirt basique',
                'subtitle' => 'Le T-Shirt basique parfait pour les hommes',
                'price' => 990,
                'imageName' => 'tshirt1.jpg',
            ],
        ];

        $products = $seedProducts;
        $imageNames = array_column($seedProducts, 'imageName');

        for ($i = count($seedProducts); $i < 1000; ++$i) {
            $products[] = [
                'title' => $faker->unique()->sentence(3),
                'subtitle' => $faker->sentence($faker->numberBetween(3, 6)),
                'price' => $faker->numberBetween(500, 10000),
                'imageName' => $imageNames[array_rand($imageNames)],
            ];
        }

        foreach ($products as $index => $value) {
            $product = new Product();
            $product->setId(Uuid::uuid4());

            $title = $value['title'];
            $product->setTitle($title);
            $product->setSlug($this->generateSlug($title));

            $product->setSubtitle($value['subtitle']);
            $product->setDescription($faker->realText($faker->numberBetween(100, 1000)));
            $product->setPrice($value['price']);
            $product->setImageName($value['imageName']);

            $categoryLevel = $faker->numberBetween(0, 3);
            $categoryReference = 'shop_category_level_' . $categoryLevel . '_' . $faker->numberBetween(1, constant(CategoryFixtures::class . '::NB_LEVEL_' . $categoryLevel));
            $category = $this->getReference($categoryReference, Category::class);
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
        return ['dev'];
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
}
