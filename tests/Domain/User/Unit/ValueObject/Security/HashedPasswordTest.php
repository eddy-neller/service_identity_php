<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\Unit\ValueObject\Security;

use App\Domain\User\ValueObject\Security\HashedPassword;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class HashedPasswordTest extends TestCase
{
    public function testFromStringCreatesValidHashedPassword(): void
    {
        $hash = '$2y$10$abcdefghijklmnopqrstuv';
        $password = HashedPassword::fromString($hash);

        $this->assertSame($hash, $password->toString());
    }

    public function testFromStringThrowsWhenEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Password hash cannot be empty.');

        HashedPassword::fromString('');
    }

    public function testFromStringThrowsWhenOnlyWhitespace(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Password hash cannot be empty.');

        HashedPassword::fromString('   ');
    }

    public function testFromStringAcceptsAnyNonEmptyString(): void
    {
        $password = HashedPassword::fromString('simple-hash');

        $this->assertSame('simple-hash', $password->toString());
    }

    public function testEqualsReturnsTrueForSameValue(): void
    {
        $this->assertTrue(
            HashedPassword::fromString('hash')->equals(HashedPassword::fromString('hash')),
        );
    }

    public function testEqualsReturnsFalseForDifferentValue(): void
    {
        $this->assertFalse(
            HashedPassword::fromString('first-hash')->equals(HashedPassword::fromString('second-hash')),
        );
    }

    public function testToStringCastsToString(): void
    {
        $hash = '$2y$10$abcdefghijklmnopqrstuv';
        $password = HashedPassword::fromString($hash);

        $this->assertSame($hash, (string) $password);
    }
}
