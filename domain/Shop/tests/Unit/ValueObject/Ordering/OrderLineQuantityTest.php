<?php

declare(strict_types=1);

namespace App\Domain\Shop\Tests\Unit\ValueObject\Ordering;

use App\Domain\Shop\Ordering\Exception\InvalidOrderLineQuantityException;
use App\Domain\Shop\Ordering\ValueObject\OrderLineQuantity;
use PHPUnit\Framework\TestCase;

final class OrderLineQuantityTest extends TestCase
{
    public function testFromIntCreatesQuantity(): void
    {
        $quantity = OrderLineQuantity::fromInt(3);

        $this->assertSame(3, $quantity->toInt());
    }

    public function testFromIntThrowsWhenNotPositive(): void
    {
        $this->expectException(InvalidOrderLineQuantityException::class);
        $this->expectExceptionMessage('Order line quantity must be greater than zero.');

        OrderLineQuantity::fromInt(0);
    }

    public function testEqualsReturnsTrueForSameValue(): void
    {
        $this->assertTrue(OrderLineQuantity::fromInt(2)->equals(OrderLineQuantity::fromInt(2)));
    }

    public function testEqualsReturnsFalseForDifferentValue(): void
    {
        $this->assertFalse(OrderLineQuantity::fromInt(2)->equals(OrderLineQuantity::fromInt(3)));
    }

    public function testToStringCastsToString(): void
    {
        $this->assertSame('2', (string) OrderLineQuantity::fromInt(2));
    }
}
