<?php

declare(strict_types=1);

namespace App\Domain\Shop\Tests\Unit\ValueObject\Ordering;

use App\Domain\Shop\Ordering\ValueObject\CartId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CartIdTest extends TestCase
{
    public function testValidIdentifier(): void
    {
        $value = '550e8400-e29b-41d4-a716-446655440000';
        self::assertSame($value, CartId::fromString($value)->toString());
    }

    public function testInvalidIdentifierThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CartId::fromString('invalid');
    }
}
