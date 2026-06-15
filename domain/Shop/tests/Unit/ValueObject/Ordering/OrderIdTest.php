<?php

declare(strict_types=1);

namespace App\Domain\Shop\Tests\Unit\ValueObject\Ordering;

use App\Domain\Shop\Ordering\ValueObject\OrderId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OrderIdTest extends TestCase
{
    private const string UUID = '123e4567-e89b-12d3-a456-426614174000';

    public function testFromStringCreatesValidOrderId(): void
    {
        $id = OrderId::fromString(self::UUID);

        $this->assertSame(self::UUID, $id->toString());
    }

    public function testFromStringThrowsWhenEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('OrderId cannot be empty.');

        OrderId::fromString('');
    }

    public function testFromStringThrowsWhenInvalidUuid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('OrderId must be a valid UUID.');

        OrderId::fromString('not-a-uuid');
    }

    public function testEqualsReturnsTrueForSameValue(): void
    {
        $id1 = OrderId::fromString(self::UUID);
        $id2 = OrderId::fromString(self::UUID);

        $this->assertTrue($id1->equals($id2));
    }

    public function testEqualsReturnsFalseForDifferentValue(): void
    {
        $id1 = OrderId::fromString(self::UUID);
        $id2 = OrderId::fromString('123e4567-e89b-12d3-a456-426614174001');

        $this->assertFalse($id1->equals($id2));
    }

    public function testToStringCastsToString(): void
    {
        $id = OrderId::fromString(self::UUID);

        $this->assertSame(self::UUID, (string) $id);
    }
}
