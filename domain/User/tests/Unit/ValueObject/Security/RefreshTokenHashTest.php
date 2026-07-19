<?php

declare(strict_types=1);

namespace App\Domain\User\Tests\Unit\ValueObject\Security;

use App\Domain\User\Exception\Security\InvalidRefreshTokenHashException;
use App\Domain\User\ValueObject\Security\RefreshTokenHash;
use PHPUnit\Framework\TestCase;

final class RefreshTokenHashTest extends TestCase
{
    public function testHashesCompareByValue(): void
    {
        $this->assertTrue(RefreshTokenHash::fromString('hash')->equals(RefreshTokenHash::fromString('hash')));
    }

    public function testEmptyHashIsRejected(): void
    {
        $this->expectException(InvalidRefreshTokenHashException::class);
        $this->expectExceptionMessage('A refresh token hash cannot be empty.');

        RefreshTokenHash::fromString('');
    }
}
