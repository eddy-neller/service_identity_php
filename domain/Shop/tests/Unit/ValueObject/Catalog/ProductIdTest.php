<?php

declare(strict_types=1);

namespace App\Domain\Shop\Tests\Unit\ValueObject\Catalog;

use App\Domain\SharedKernel\Exception\InvalidUuidException;
use App\Domain\Shop\Catalog\ValueObject\ProductId;
use PHPUnit\Framework\TestCase;

final class ProductIdTest extends TestCase
{
    private const string UUID = '123e4567-e89b-12d3-a456-426614174000';

    public function testFromStringCreatesValidProductId(): void
    {
        $id = ProductId::fromString(self::UUID);

        $this->assertSame(self::UUID, $id->toString());
    }

    public function testFromStringThrowsWhenEmpty(): void
    {
        $this->expectException(InvalidUuidException::class);
        $this->expectExceptionMessage('Invalid ProductId: ');

        ProductId::fromString('');
    }

    public function testFromStringThrowsWhenInvalidUuid(): void
    {
        $this->expectException(InvalidUuidException::class);
        $this->expectExceptionMessage('Invalid ProductId: not-a-uuid');

        ProductId::fromString('not-a-uuid');
    }

    public function testEqualsReturnsTrueForSameValue(): void
    {
        $id1 = ProductId::fromString(self::UUID);
        $id2 = ProductId::fromString(self::UUID);

        $this->assertTrue($id1->equals($id2));
    }

    public function testEqualsReturnsFalseForDifferentValue(): void
    {
        $id1 = ProductId::fromString(self::UUID);
        $id2 = ProductId::fromString('123e4567-e89b-12d3-a456-426614174001');

        $this->assertFalse($id1->equals($id2));
    }

    public function testToStringCastsToString(): void
    {
        $id = ProductId::fromString(self::UUID);

        $this->assertSame(self::UUID, (string) $id);
    }
}
