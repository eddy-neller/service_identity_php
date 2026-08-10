<?php

declare(strict_types=1);

namespace App\Tests\Application\Unit\User\UseCase\Command\Auth;

use App\Domain\User\Model\RefreshToken;
use App\Domain\User\ValueObject\Identity\UserId;
use App\Domain\User\ValueObject\Security\RefreshTokenHash;
use App\Domain\User\ValueObject\Security\RefreshTokenId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RefreshTokenTest extends TestCase
{
    public function testRefreshTokenExpiresAtItsExpirationInstant(): void
    {
        $expiresAt = new DateTimeImmutable('2026-07-19 10:00:00');
        $token = RefreshToken::issue(
            RefreshTokenId::fromString('550e8400-e29b-41d4-a716-446655440001'),
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            RefreshTokenHash::fromString('hash'),
            $expiresAt,
            new DateTimeImmutable('2026-07-18 10:00:00'),
        );

        $this->assertTrue($token->isExpired($expiresAt));
    }
}
