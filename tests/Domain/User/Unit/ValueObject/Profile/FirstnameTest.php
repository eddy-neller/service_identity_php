<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\Unit\ValueObject\Profile;

use App\Domain\User\Exception\Profile\InvalidFirstnameException;
use App\Domain\User\ValueObject\Profile\Firstname;
use PHPUnit\Framework\TestCase;

final class FirstnameTest extends TestCase
{
    public function testConstructCreatesValidFirstname(): void
    {
        $firstname = Firstname::fromString('John');

        $this->assertSame('John', $firstname->toString());
    }

    public function testConstructTrimsWhitespace(): void
    {
        $firstname = Firstname::fromString('  John  ');

        $this->assertSame('John', $firstname->toString());
    }

    public function testConstructThrowsExceptionWhenEmpty(): void
    {
        $this->expectException(InvalidFirstnameException::class);
        $this->expectExceptionMessage('First name cannot be empty.');

        Firstname::fromString('');
    }

    public function testConstructThrowsExceptionWhenOnlyWhitespace(): void
    {
        $this->expectException(InvalidFirstnameException::class);
        $this->expectExceptionMessage('First name cannot be empty.');

        Firstname::fromString('   ');
    }

    public function testConstructThrowsExceptionWhenTooShort(): void
    {
        $this->expectException(InvalidFirstnameException::class);
        $this->expectExceptionMessage('First name must contain at least 2 characters.');

        Firstname::fromString('A');
    }

    public function testConstructThrowsExceptionWhenTooLong(): void
    {
        $this->expectException(InvalidFirstnameException::class);
        $this->expectExceptionMessage('First name cannot exceed 50 characters.');

        Firstname::fromString(str_repeat('a', 51));
    }

    public function testConstructAcceptsMinimumLength(): void
    {
        $firstname = Firstname::fromString('Jo');

        $this->assertSame('Jo', $firstname->toString());
    }

    public function testConstructAcceptsMaximumLength(): void
    {
        $value = str_repeat('a', 50);
        $firstname = Firstname::fromString($value);

        $this->assertSame($value, $firstname->toString());
    }

    public function testConstructHandlesMultibyteCharacters(): void
    {
        $firstname = Firstname::fromString('François');

        $this->assertSame('François', $firstname->toString());
    }

    public function testEqualsReturnsTrueForSameValue(): void
    {
        $firstname1 = Firstname::fromString('John');
        $firstname2 = Firstname::fromString('John');

        $this->assertTrue($firstname1->equals($firstname2));
    }

    public function testEqualsReturnsFalseForDifferentValue(): void
    {
        $firstname1 = Firstname::fromString('John');
        $firstname2 = Firstname::fromString('Jane');

        $this->assertFalse($firstname1->equals($firstname2));
    }

    public function testToStringReturnsValue(): void
    {
        $firstname = Firstname::fromString('John');

        $this->assertSame('John', (string) $firstname);
    }
}
