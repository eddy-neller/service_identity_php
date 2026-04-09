<?php

declare(strict_types=1);

namespace App\Domain\User\Tests\Unit\ValueObject\Identity;

use App\Domain\User\Exception\InvalidLastnameException;
use App\Domain\User\Identity\ValueObject\Lastname;
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
        $this->expectExceptionMessage('Le nom ne peut pas être vide.');

        Lastname::fromString('');
    }

    public function testConstructThrowsExceptionWhenOnlyWhitespace(): void
    {
        $this->expectException(InvalidLastnameException::class);
        $this->expectExceptionMessage('Le nom ne peut pas être vide.');

        Lastname::fromString('   ');
    }

    public function testConstructThrowsExceptionWhenTooShort(): void
    {
        $this->expectException(InvalidLastnameException::class);
        $this->expectExceptionMessage('Le nom doit contenir au moins 2 caractères.');

        Lastname::fromString('A');
    }

    public function testConstructThrowsExceptionWhenTooLong(): void
    {
        $this->expectException(InvalidLastnameException::class);
        $this->expectExceptionMessage('Le nom ne peut pas dépasser 50 caractères.');

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
