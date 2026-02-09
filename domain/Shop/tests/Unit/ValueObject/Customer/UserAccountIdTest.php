<?php

declare(strict_types=1);

namespace App\Domain\Shop\Tests\Unit\ValueObject\Customer;

use App\Domain\Shop\Customer\ValueObject\UserAccountId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class UserAccountIdTest extends TestCase
{
    private const string UUID = '123e4567-e89b-12d3-a456-426614174000';

    public function testFromStringCreatesValidUserAccountId(): void
    {
        $id = UserAccountId::fromString(self::UUID);

        $this->assertSame(self::UUID, $id->toString());
    }

    public function testToStringMagicMethodReturnsUuid(): void
    {
        $id = UserAccountId::fromString(self::UUID);

        $this->assertSame(self::UUID, (string) $id);
    }

    public function testFromStringThrowsWhenEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('UserAccountId cannot be empty.');

        UserAccountId::fromString('');
    }

    public function testFromStringThrowsWhenInvalidUuid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('UserAccountId must be a valid UUID.');

        UserAccountId::fromString('not-a-uuid');
    }
}
