<?php

declare(strict_types=1);

namespace App\Domain\Shop\Tests\Unit\ValueObject\Customer;

use App\Domain\Shop\Customer\ValueObject\CustomerId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CustomerIdTest extends TestCase
{
    private const string UUID = '123e4567-e89b-12d3-a456-426614174000';

    public function testFromStringCreatesValidCustomerId(): void
    {
        $id = CustomerId::fromString(self::UUID);

        $this->assertSame(self::UUID, $id->toString());
    }

    public function testToStringMagicMethodReturnsUuid(): void
    {
        $id = CustomerId::fromString(self::UUID);

        $this->assertSame(self::UUID, (string) $id);
    }

    public function testFromStringThrowsWhenEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CustomerId cannot be empty.');

        CustomerId::fromString('');
    }

    public function testFromStringThrowsWhenInvalidUuid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CustomerId must be a valid UUID.');

        CustomerId::fromString('not-a-uuid');
    }
}
