<?php

declare(strict_types=1);

namespace App\Domain\User\Tests\Unit\ValueObject;

use App\Domain\User\Exception\InvalidEmailAddressException;
use App\Domain\User\ValueObject\EmailAddress;
use PHPUnit\Framework\TestCase;

final class EmailAddressTest extends TestCase
{
    public function testConstructCreatesValidEmailAddress(): void
    {
        $email = EmailAddress::fromString('john@example.com');

        $this->assertSame('john@example.com', $email->toString());
    }

    public function testConstructNormalizesToLowercase(): void
    {
        $email = EmailAddress::fromString('John@Example.COM');

        $this->assertSame('john@example.com', $email->toString());
    }

    public function testConstructTrimsWhitespace(): void
    {
        $email = EmailAddress::fromString('  john@example.com  ');

        $this->assertSame('john@example.com', $email->toString());
    }

    public function testConstructThrowsExceptionForInvalidEmail(): void
    {
        $this->expectException(InvalidEmailAddressException::class);
        $this->expectExceptionMessage('Email address is invalid.');

        EmailAddress::fromString('invalid-email');
    }

    public function testConstructThrowsExceptionForEmptyEmail(): void
    {
        $this->expectException(InvalidEmailAddressException::class);
        $this->expectExceptionMessage('Email address is invalid.');

        EmailAddress::fromString('');
    }

    public function testConstructThrowsExceptionForEmailWithoutAt(): void
    {
        $this->expectException(InvalidEmailAddressException::class);
        $this->expectExceptionMessage('Email address is invalid.');

        EmailAddress::fromString('johndomain.com');
    }

    public function testConstructThrowsExceptionForEmailWithoutDomain(): void
    {
        $this->expectException(InvalidEmailAddressException::class);
        $this->expectExceptionMessage('Email address is invalid.');

        EmailAddress::fromString('john@');
    }

    public function testEqualsReturnsTrueForSameValue(): void
    {
        $email1 = EmailAddress::fromString('john@example.com');
        $email2 = EmailAddress::fromString('john@example.com');

        $this->assertTrue($email1->equals($email2));
    }

    public function testEqualsReturnsTrueForCaseInsensitiveMatch(): void
    {
        $email1 = EmailAddress::fromString('John@Example.com');
        $email2 = EmailAddress::fromString('john@example.com');

        $this->assertTrue($email1->equals($email2));
    }

    public function testEqualsReturnsFalseForDifferentValue(): void
    {
        $email1 = EmailAddress::fromString('john@example.com');
        $email2 = EmailAddress::fromString('jane@example.com');

        $this->assertFalse($email1->equals($email2));
    }

    public function testToStringReturnsValue(): void
    {
        $email = EmailAddress::fromString('john@example.com');

        $this->assertSame('john@example.com', (string) $email);
    }
}
