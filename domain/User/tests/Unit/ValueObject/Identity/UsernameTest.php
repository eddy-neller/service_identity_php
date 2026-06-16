<?php

declare(strict_types=1);

namespace App\Domain\User\Tests\Unit\ValueObject\Identity;

use App\Domain\User\Exception\InvalidUsernameException;
use App\Domain\User\Identity\ValueObject\Username;
use PHPUnit\Framework\TestCase;

final class UsernameTest extends TestCase
{
    public function testConstructCreatesValidUsername(): void
    {
        $username = Username::fromString('john');

        $this->assertSame('john', $username->toString());
    }

    public function testConstructTrimsWhitespace(): void
    {
        $username = Username::fromString('  john  ');

        $this->assertSame('john', $username->toString());
    }

    public function testConstructThrowsExceptionWhenEmpty(): void
    {
        $this->expectException(InvalidUsernameException::class);
        $this->expectExceptionMessage('Username cannot be empty.');

        Username::fromString('');
    }

    public function testConstructThrowsExceptionWhenOnlyWhitespace(): void
    {
        $this->expectException(InvalidUsernameException::class);
        $this->expectExceptionMessage('Username cannot be empty.');

        Username::fromString('   ');
    }

    public function testConstructThrowsExceptionWhenTooShort(): void
    {
        $this->expectException(InvalidUsernameException::class);
        $this->expectExceptionMessage('Username must contain at least 2 characters.');

        Username::fromString('a');
    }

    public function testConstructThrowsExceptionWhenTooLong(): void
    {
        $this->expectException(InvalidUsernameException::class);
        $this->expectExceptionMessage('Username cannot exceed 20 characters.');

        Username::fromString(str_repeat('a', 21));
    }

    public function testConstructAcceptsMinimumLength(): void
    {
        $username = Username::fromString('ab');

        $this->assertSame('ab', $username->toString());
    }

    public function testConstructAcceptsMaximumLength(): void
    {
        $value = str_repeat('a', 20);
        $username = Username::fromString($value);

        $this->assertSame($value, $username->toString());
    }

    public function testConstructHandlesMultibyteCharacters(): void
    {
        $username = Username::fromString('été');

        $this->assertSame('été', $username->toString());
    }

    public function testEqualsReturnsTrueForSameValue(): void
    {
        $username1 = Username::fromString('john');
        $username2 = Username::fromString('john');

        $this->assertTrue($username1->equals($username2));
    }

    public function testEqualsReturnsFalseForDifferentValue(): void
    {
        $username1 = Username::fromString('john');
        $username2 = Username::fromString('jane');

        $this->assertFalse($username1->equals($username2));
    }

    public function testToStringReturnsValue(): void
    {
        $username = Username::fromString('john');

        $this->assertSame('john', (string) $username);
    }
}
