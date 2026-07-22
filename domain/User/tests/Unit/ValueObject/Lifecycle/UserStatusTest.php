<?php

declare(strict_types=1);

namespace App\Domain\User\Tests\Unit\ValueObject\Lifecycle;

use App\Domain\User\Exception\Lifecycle\InvalidUserStatusException;
use App\Domain\User\ValueObject\Lifecycle\UserStatus;
use PHPUnit\Framework\TestCase;

final class UserStatusTest extends TestCase
{
    public function testFromIntRejectsUnsupportedValue(): void
    {
        $this->expectException(InvalidUserStatusException::class);

        UserStatus::fromInt(42);
    }

    public function testFromIntRejectsNegativeValue(): void
    {
        $this->expectException(InvalidUserStatusException::class);

        UserStatus::fromInt(-1);
    }

    public function testFromIntWithInactiveValue(): void
    {
        $status = UserStatus::fromInt(UserStatus::INACTIVE);

        $this->assertSame(UserStatus::INACTIVE, $status->toInt());
    }

    public function testFromIntWithActiveValue(): void
    {
        $status = UserStatus::fromInt(UserStatus::ACTIVE);

        $this->assertSame(UserStatus::ACTIVE, $status->toInt());
    }

    public function testInactiveCreatesInactiveStatus(): void
    {
        $status = UserStatus::inactive();

        $this->assertSame(UserStatus::INACTIVE, $status->toInt());
    }

    public function testFromIntCreatesStatusWithValue(): void
    {
        $status = UserStatus::fromInt(UserStatus::BLOCKED);

        $this->assertSame(UserStatus::BLOCKED, $status->toInt());
    }

    public function testAddFlagAddsFlag(): void
    {
        $status = UserStatus::inactive();
        $newStatus = UserStatus::active();

        $this->assertSame(UserStatus::INACTIVE, $status->toInt());
        $this->assertSame(UserStatus::ACTIVE, $newStatus->toInt());
    }

    public function testAddFlagIsImmutable(): void
    {
        $status = UserStatus::inactive();
        $newStatus = UserStatus::active();

        $this->assertNotSame($status, $newStatus);
        $this->assertSame(UserStatus::INACTIVE, $status->toInt());
    }

    public function testRemoveFlagRemovesFlag(): void
    {
        $status = UserStatus::blocked();

        $this->assertTrue($status->isBlocked());
        $this->assertFalse($status->isActive());
    }

    public function testRemoveFlagIsImmutable(): void
    {
        $status = UserStatus::blocked();
        $newStatus = UserStatus::active();

        $this->assertNotSame($status, $newStatus);
        $this->assertTrue($status->isBlocked());
        $this->assertTrue($newStatus->isActive());
    }

    public function testHasFlagReturnsTrueWhenFlagIsSet(): void
    {
        $status = UserStatus::active();

        $this->assertTrue($status->isActive());
    }

    public function testHasFlagReturnsFalseWhenFlagIsNotSet(): void
    {
        $status = UserStatus::inactive();

        $this->assertFalse($status->isActive());
    }

    public function testCanCombineMultipleFlags(): void
    {
        $status = UserStatus::blocked();

        $this->assertTrue($status->isBlocked());
        $this->assertFalse($status->isActive());
    }

    public function testToIntReturnsIntValue(): void
    {
        $status = UserStatus::fromInt(UserStatus::ACTIVE);

        $this->assertSame(UserStatus::ACTIVE, $status->toInt());
    }
}
