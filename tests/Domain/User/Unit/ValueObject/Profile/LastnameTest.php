<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\Unit\ValueObject\Profile;

use App\Domain\User\Exception\Profile\InvalidLastnameException;
use App\Domain\User\ValueObject\Profile\Lastname;
use PHPUnit\Framework\TestCase;

final class LastnameTest extends TestCase
{
    public function testConstructCreatesValidLastname(): void
    {
        $lastname = Lastname::fromString('Doe');

        $this->assertSame('Doe', $lastname->toString());
    }

    public function testConstructTrimsWhitespace(): void
    {
        $lastname = Lastname::fromString('  Doe  ');

        $this->assertSame('Doe', $lastname->toString());
    }

    public function testConstructThrowsExceptionWhenEmpty(): void
    {
        $this->expectException(InvalidLastnameException::class);
        $this->expectExceptionMessage('Last name cannot be empty.');

        Lastname::fromString('');
    }

    public function testConstructThrowsExceptionWhenOnlyWhitespace(): void
    {
        $this->expectException(InvalidLastnameException::class);
        $this->expectExceptionMessage('Last name cannot be empty.');

        Lastname::fromString('   ');
    }

    public function testConstructThrowsExceptionWhenTooShort(): void
    {
        $this->expectException(InvalidLastnameException::class);
        $this->expectExceptionMessage('Last name must contain at least 2 characters.');

        Lastname::fromString('A');
    }

    public function testConstructThrowsExceptionWhenTooLong(): void
    {
        $this->expectException(InvalidLastnameException::class);
        $this->expectExceptionMessage('Last name cannot exceed 50 characters.');

        Lastname::fromString(str_repeat('a', 51));
    }

    public function testConstructAcceptsMinimumLength(): void
    {
        $lastname = Lastname::fromString('Do');

        $this->assertSame('Do', $lastname->toString());
    }

    public function testConstructAcceptsMaximumLength(): void
    {
        $value = str_repeat('a', 50);
        $lastname = Lastname::fromString($value);

        $this->assertSame($value, $lastname->toString());
    }

    public function testConstructHandlesMultibyteCharacters(): void
    {
        $lastname = Lastname::fromString('Müller');

        $this->assertSame('Müller', $lastname->toString());
    }

    public function testEqualsReturnsTrueForSameValue(): void
    {
        $lastname1 = Lastname::fromString('Doe');
        $lastname2 = Lastname::fromString('Doe');

        $this->assertTrue($lastname1->equals($lastname2));
    }

    public function testEqualsReturnsFalseForDifferentValue(): void
    {
        $lastname1 = Lastname::fromString('Doe');
        $lastname2 = Lastname::fromString('Smith');

        $this->assertFalse($lastname1->equals($lastname2));
    }

    public function testToStringReturnsValue(): void
    {
        $lastname = Lastname::fromString('Doe');

        $this->assertSame('Doe', (string) $lastname);
    }
}
