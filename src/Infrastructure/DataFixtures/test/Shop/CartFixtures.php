<?php

declare(strict_types=1);

namespace App\Infrastructure\DataFixtures\test\Shop;

use App\Infrastructure\DataFixtures\DataFixturesTrait;
use App\Infrastructure\Entity\Shop\Cart;
use App\Infrastructure\Entity\Shop\CartLine;
use App\Infrastructure\Entity\Shop\Customer;
use App\Infrastructure\Entity\Shop\Product;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Ramsey\Uuid\Uuid;

final class CartFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    use DataFixturesTrait;

    public function load(ObjectManager $manager): void
    {
        $this->createCart($manager, 'cart_member', 'customer_member', [
            ['shop_product_1', 2],
            ['shop_product_2', 1],
        ]);
        $this->createCart($manager, 'cart_admin', 'customer_admin', [
            ['shop_product_3', 1],
        ]);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            CustomerFixtures::class,
            ProductFixtures::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['test'];
    }

    /**
     * @param list<array{0: string, 1: int}> $lines
     */
    private function createCart(ObjectManager $manager, string $reference, string $customerReference, array $lines): void
    {
        $cart = new Cart();
        $cart->setId(Uuid::uuid4());

        /** @var Customer $customer */
        $customer = $this->getReference($customerReference, Customer::class);
        $cart->setCustomer($customer);

        $timestamps = $this->generateTimestamps();
        $cart->setCreatedAt($timestamps['createdAt']);
        $cart->setUpdatedAt($timestamps['updatedAt']);

        foreach ($lines as [$productReference, $quantity]) {
            $cart->addLine($this->createLine($productReference, $quantity));
        }

        $this->addReference($reference, $cart);

        $manager->persist($cart);
    }

    private function createLine(string $productReference, int $quantity): CartLine
    {
        /** @var Product $product */
        $product = $this->getReference($productReference, Product::class);

        $line = new CartLine();
        $line->setId(Uuid::uuid4());
        $line->setProduct($product);
        $line->setQuantity($quantity);

        return $line;
    }
}
