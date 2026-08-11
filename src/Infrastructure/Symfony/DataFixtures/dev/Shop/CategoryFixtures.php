<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\DataFixtures\dev\Shop;

use App\Infrastructure\Persistence\Doctrine\Shop\Catalog\CategoryEntity as Category;
use App\Infrastructure\Symfony\DataFixtures\DataFixturesTrait;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;
use Ramsey\Uuid\Uuid;
use Symfony\Component\String\Slugger\AsciiSlugger;

class CategoryFixtures extends Fixture implements FixtureGroupInterface
{
    use DataFixturesTrait;

    private AsciiSlugger $slugger;

    public const int NB_LEVEL_0 = 2;

    public const int NB_LEVEL_1 = 4;

    public const int NB_LEVEL_2 = 8;

    public const int NB_LEVEL_3 = 16;

    public function __construct()
    {
        $this->slugger = new AsciiSlugger();
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create();
        $level1Categories = [];
        $level2Categories = [];
        $level3Categories = [];

        // Générer les catégories racine (niveau 0)
        for ($i = 1; $i <= self::NB_LEVEL_0; ++$i) {
            $category = $this->createCategory($faker, null);

            $this->addReference('shop_category_level_0_' . $i, $category);
            $level1Categories[] = $category;

            $manager->persist($category);
        }

        // Générer les catégories de niveau 1
        $level2Index = 1;
        foreach ($level1Categories as $parent) {
            if ($level2Index > self::NB_LEVEL_1) {
                break;
            }

            $category = $this->createCategory($faker, $parent);
            $this->addReference('shop_category_level_1_' . $level2Index, $category);
            $level2Categories[] = $category;
            $manager->persist($category);
            ++$level2Index;
        }

        for ($i = $level2Index; $i <= self::NB_LEVEL_1; ++$i) {
            $parent = $level1Categories[$faker->numberBetween(0, count($level1Categories) - 1)];
            $category = $this->createCategory($faker, $parent);
            $this->addReference('shop_category_level_1_' . $i, $category);
            $level2Categories[] = $category;
            $manager->persist($category);
        }

        // Générer les catégories de niveau 2
        $level3Index = 1;
        foreach ($level2Categories as $parent) {
            if ($level3Index > self::NB_LEVEL_2) {
                break;
            }

            $category = $this->createCategory($faker, $parent);
            $this->addReference('shop_category_level_2_' . $level3Index, $category);
            $level3Categories[] = $category;
            $manager->persist($category);
            ++$level3Index;
        }

        for ($i = $level3Index; $i <= self::NB_LEVEL_2; ++$i) {
            $parent = $level2Categories[$faker->numberBetween(0, count($level2Categories) - 1)];
            $category = $this->createCategory($faker, $parent);
            $this->addReference('shop_category_level_2_' . $i, $category);
            $level3Categories[] = $category;
            $manager->persist($category);
        }

        // Générer les catégories de niveau 3
        $level4Index = 1;
        foreach ($level3Categories as $parent) {
            if ($level4Index > self::NB_LEVEL_3) {
                break;
            }

            $category = $this->createCategory($faker, $parent);
            $this->addReference('shop_category_level_3_' . $level4Index, $category);
            $manager->persist($category);
            ++$level4Index;
        }

        for ($i = $level4Index; $i <= self::NB_LEVEL_3; ++$i) {
            $parent = $level3Categories[$faker->numberBetween(0, count($level3Categories) - 1)];
            $category = $this->createCategory($faker, $parent);
            $this->addReference('shop_category_level_3_' . $i, $category);
            $manager->persist($category);
        }

        $manager->flush();
    }

    private function createCategory(Generator $faker, ?Category $parent): Category
    {
        $category = new Category();
        $category->setId(Uuid::uuid4());

        $title = $faker->company();
        $category->setTitle($title);
        $category->setDescription($faker->text());
        $category->setSlug($this->generateSlug($title));

        if (null !== $parent) {
            $category->setParent($parent);
        }

        $this->assignTimestamps($category);

        return $category;
    }

    private function assignTimestamps(Category $category): void
    {
        $timestamps = $this->generateTimestamps();
        $category->setCreatedAt($timestamps['createdAt']);
        $category->setUpdatedAt($timestamps['updatedAt']);
    }

    private function generateSlug(string $title): string
    {
        return $this->slugger->slug($title)->lower()->toString();
    }

    public static function getGroups(): array
    {
        return ['dev'];
    }
}
