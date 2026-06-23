<?php

declare(strict_types=1);

namespace App\Domain\User\Tests\Unit\ValueObject;

use App\Domain\User\ValueObject\UserId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class UserIdTest extends TestCase
{
    public function testFromStringCreatesValidUserId(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $userId = UserId::fromString($uuid);

        $this->assertSame($uuid, $userId->toString());
    }

    public function testFromStringTrimsWhitespace(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $userId = UserId::fromString(sprintf('  %s  ', $uuid));

        $this->assertSame($uuid, $userId->toString());
    }

    public function testFromStringThrowsExceptionWhenEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('UserId cannot be empty.');

        UserId::fromString('');
    }

    public function testFromStringThrowsExceptionWhenOnlyWhitespace(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('UserId cannot be empty.');

        UserId::fromString('   ');
    }

    public function testFromStringThrowsExceptionForInvalidUuid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('UserId must be a valid UUID.');

        UserId::fromString('invalid-uuid');
    }

    public function testFromStringThrowsExceptionForUuidWithInvalidVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('UserId must be a valid UUID.');

        // Version 0 n'est pas valide (doit être 1-5)
        UserId::fromString('550e8400-e29b-01d4-0716-446655440000');
    }

    public function testFromStringThrowsExceptionForUuidWithInvalidVariant(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('UserId must be a valid UUID.');

        // Variant invalide (doit être 8, 9, a, b, A, B)
        UserId::fromString('550e8400-e29b-41d4-c716-446655440000');
    }

    public function testEqualsReturnsTrueForSameValue(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $userId1 = UserId::fromString($uuid);
        $userId2 = UserId::fromString($uuid);

        $this->assertTrue($userId1->equals($userId2));
    }

    public function testEqualsReturnsFalseForDifferentValue(): void
    {
        $userId1 = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $userId2 = UserId::fromString('550e8400-e29b-41d4-a716-446655440001');

        $this->assertFalse($userId1->equals($userId2));
    }

    public function testToStringReturnsValue(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $userId = UserId::fromString($uuid);

        $this->assertSame($uuid, (string) $userId);
    }

    public function testAcceptsValidUuidV1(): void
    {
        $userId = UserId::fromString('550e8400-e29b-11d4-a716-446655440000');

        $this->assertSame('550e8400-e29b-11d4-a716-446655440000', $userId->toString());
    }

    public function testAcceptsValidUuidV4(): void
    {
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');

        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $userId->toString());
    }

    public function testAcceptsValidUuidV5(): void
    {
        $userId = UserId::fromString('550e8400-e29b-51d4-a716-446655440000');

        $this->assertSame('550e8400-e29b-51d4-a716-446655440000', $userId->toString());
    }
}
