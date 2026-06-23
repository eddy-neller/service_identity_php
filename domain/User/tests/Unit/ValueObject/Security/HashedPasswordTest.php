<?php

declare(strict_types=1);

namespace App\Domain\User\Tests\Unit\ValueObject\Security;

use App\Domain\User\ValueObject\Security\HashedPassword;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class HashedPasswordTest extends TestCase
{
    public function testConstructCreatesValidHashedPassword(): void
    {
        $hash = '$2y$10$abcdefghijklmnopqrstuv';
        $password = new HashedPassword($hash);

        $this->assertSame($hash, $password->toString());
    }

    public function testConstructThrowsExceptionWhenEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Le mot de passe haché ne peut pas être vide.');

        new HashedPassword('');
    }

    public function testConstructThrowsExceptionWhenOnlyWhitespace(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Le mot de passe haché ne peut pas être vide.');

        new HashedPassword('   ');
    }

    public function testConstructAcceptsAnyNonEmptyString(): void
    {
        $password = new HashedPassword('simple-hash');

        $this->assertSame('simple-hash', $password->toString());
    }

    public function testToStringReturnsValue(): void
    {
        $hash = '$2y$10$abcdefghijklmnopqrstuv';
        $password = new HashedPassword($hash);

        $this->assertSame($hash, $password->toString());
    }
}
