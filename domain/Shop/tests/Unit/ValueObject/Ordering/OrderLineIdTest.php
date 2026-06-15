<?php

declare(strict_types=1);

namespace App\Domain\Shop\Tests\Unit\ValueObject\Ordering;

use App\Domain\Shop\Ordering\ValueObject\OrderLineId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OrderLineIdTest extends TestCase
{
    private const string UUID = '123e4567-e89b-12d3-a456-426614174000';

    public function testFromStringCreatesValidOrderLineId(): void
    {
        $id = OrderLineId::fromString(self::UUID);

        $this->assertSame(self::UUID, $id->toString());
    }

    public function testFromStringThrowsWhenEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('OrderLineId cannot be empty.');

        OrderLineId::fromString('');
    }

    public function testFromStringThrowsWhenInvalidUuid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('OrderLineId must be a valid UUID.');

        OrderLineId::fromString('not-a-uuid');
    }

    public function testEqualsReturnsTrueForSameValue(): void
    {
        $id1 = OrderLineId::fromString(self::UUID);
        $id2 = OrderLineId::fromString(self::UUID);

        $this->assertTrue($id1->equals($id2));
    }

    public function testEqualsReturnsFalseForDifferentValue(): void
    {
        $id1 = OrderLineId::fromString(self::UUID);
        $id2 = OrderLineId::fromString('123e4567-e89b-12d3-a456-426614174001');

        $this->assertFalse($id1->equals($id2));
    }

    public function testToStringCastsToString(): void
    {
        $id = OrderLineId::fromString(self::UUID);

        $this->assertSame(self::UUID, (string) $id);
    }
}
