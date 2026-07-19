<?php

declare(strict_types=1);

namespace App\Domain\Shop\Tests\Unit\ValueObject\Customer;

use App\Domain\SharedKernel\Exception\InvalidUuidException;
use App\Domain\Shop\Customer\ValueObject\AddressId;
use PHPUnit\Framework\TestCase;

final class AddressIdTest extends TestCase
{
    private const string UUID = '123e4567-e89b-12d3-a456-426614174000';

    public function testFromStringCreatesValidAddressId(): void
    {
        $id = AddressId::fromString(self::UUID);

        $this->assertSame(self::UUID, $id->toString());
    }

    public function testToStringMagicMethodReturnsUuid(): void
    {
        $id = AddressId::fromString(self::UUID);

        $this->assertSame(self::UUID, (string) $id);
    }

    public function testFromStringThrowsWhenEmpty(): void
    {
        $this->expectException(InvalidUuidException::class);
        $this->expectExceptionMessage('Invalid AddressId: ');

        AddressId::fromString('');
    }

    public function testFromStringThrowsWhenInvalidUuid(): void
    {
        $this->expectException(InvalidUuidException::class);
        $this->expectExceptionMessage('Invalid AddressId: not-a-uuid');

        AddressId::fromString('not-a-uuid');
    }
}
