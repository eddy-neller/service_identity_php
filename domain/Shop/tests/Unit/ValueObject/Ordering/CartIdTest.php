<?php

declare(strict_types=1);

namespace App\Domain\Shop\Tests\Unit\ValueObject\Ordering;

use App\Domain\Shop\Ordering\ValueObject\CartId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CartIdTest extends TestCase
{
    private const string UUID = '550e8400-e29b-41d4-a716-446655440000';

    public function testFromStringCreatesValidCartId(): void
    {
        $id = CartId::fromString(self::UUID);

        $this->assertSame(self::UUID, $id->toString());
    }

    public function testFromStringThrowsWhenEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CartId cannot be empty.');

        CartId::fromString('');
    }

    public function testFromStringThrowsWhenInvalidUuid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CartId must be a valid UUID.');

        CartId::fromString('not-a-uuid');
    }

    public function testEqualsReturnsTrueForSameValue(): void
    {
        $id1 = CartId::fromString(self::UUID);
        $id2 = CartId::fromString(self::UUID);

        $this->assertTrue($id1->equals($id2));
    }

    public function testEqualsReturnsFalseForDifferentValue(): void
    {
        $id1 = CartId::fromString(self::UUID);
        $id2 = CartId::fromString('550e8400-e29b-41d4-a716-446655440001');

        $this->assertFalse($id1->equals($id2));
    }

    public function testToStringCastsToString(): void
    {
        $id = CartId::fromString(self::UUID);

        $this->assertSame(self::UUID, (string) $id);
    }
}
