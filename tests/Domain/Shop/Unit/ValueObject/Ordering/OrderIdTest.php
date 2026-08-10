<?php

declare(strict_types=1);

namespace App\Tests\Domain\Shop\Unit\ValueObject\Ordering;

use App\Domain\SharedKernel\Exception\InvalidUuidException;
use App\Domain\Shop\Ordering\ValueObject\OrderId;
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
        $this->expectException(InvalidUuidException::class);
        $this->expectExceptionMessage('Invalid OrderId: ');

        OrderId::fromString('');
    }

    public function testFromStringThrowsWhenInvalidUuid(): void
    {
        $this->expectException(InvalidUuidException::class);
        $this->expectExceptionMessage('Invalid OrderId: not-a-uuid');

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
