<?php

declare(strict_types=1);

namespace App\Domain\Shop\Tests\Unit\Model\Ordering;

use App\Domain\Shop\Catalog\ValueObject\ProductId;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Ordering\Exception\CartLineNotFoundException;
use App\Domain\Shop\Ordering\Exception\CartQuantityExceededException;
use App\Domain\Shop\Ordering\Model\Cart;
use App\Domain\Shop\Ordering\ValueObject\CartId;
use App\Domain\Shop\Ordering\ValueObject\CartLineId;
use App\Domain\Shop\Ordering\ValueObject\CartLineQuantity;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CartTest extends TestCase
{
    private const string CART_ID = '550e8400-e29b-41d4-a716-446655440000';

    private const string CUSTOMER_ID = '550e8400-e29b-41d4-a716-446655440001';

    private const string LINE_ID = '550e8400-e29b-41d4-a716-446655440002';

    private const string PRODUCT_ID = '550e8400-e29b-41d4-a716-446655440003';

    public function testAddLineMergesTheSameProduct(): void
    {
        $cart = $this->createCart();
        $now = new DateTimeImmutable();

        $cart->addLine(
            CartLineId::fromString(self::LINE_ID),
            ProductId::fromString(self::PRODUCT_ID),
            CartLineQuantity::fromInt(2),
            $now,
        );
        $cart->addLine(
            CartLineId::fromString('550e8400-e29b-41d4-a716-446655440004'),
            ProductId::fromString(self::PRODUCT_ID),
            CartLineQuantity::fromInt(3),
            $now,
        );

        self::assertCount(1, $cart->getLines());
        self::assertSame(5, $cart->getLines()[0]->getQuantity()->toInt());
    }

    public function testUpdateRemoveAndClearLines(): void
    {
        $cart = $this->createCart();
        $now = new DateTimeImmutable();
        $productId = ProductId::fromString(self::PRODUCT_ID);
        $cart->addLine(CartLineId::fromString(self::LINE_ID), $productId, CartLineQuantity::fromInt(1), $now);

        $cart->updateLine($productId, CartLineQuantity::fromInt(4), $now);
        self::assertSame(4, $cart->getLines()[0]->getQuantity()->toInt());

        $cart->removeLine($productId, $now);
        self::assertSame([], $cart->getLines());

        $cart->addLine(CartLineId::fromString(self::LINE_ID), $productId, CartLineQuantity::fromInt(1), $now);
        $cart->clear($now);
        self::assertSame([], $cart->getLines());
    }

    public function testMissingLineThrows(): void
    {
        $this->expectException(CartLineNotFoundException::class);

        $this->createCart()->removeLine(ProductId::fromString(self::PRODUCT_ID), new DateTimeImmutable());
    }

    public function testMergedQuantityCannotExceedMaximum(): void
    {
        $cart = $this->createCart();
        $now = new DateTimeImmutable();
        $productId = ProductId::fromString(self::PRODUCT_ID);
        $cart->addLine(CartLineId::fromString(self::LINE_ID), $productId, CartLineQuantity::fromInt(99), $now);

        $this->expectException(CartQuantityExceededException::class);
        $cart->addLine(CartLineId::fromString(self::LINE_ID), $productId, CartLineQuantity::fromInt(1), $now);
    }

    private function createCart(): Cart
    {
        return Cart::create(
            CartId::fromString(self::CART_ID),
            CustomerId::fromString(self::CUSTOMER_ID),
            new DateTimeImmutable(),
        );
    }
}
