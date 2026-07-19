<?php

declare(strict_types=1);

namespace App\Domain\User\Tests\Unit\Model;

use App\Domain\User\Exception\UserDomainException;
use App\Domain\User\Model\RefreshToken;
use App\Domain\User\ValueObject\RefreshTokenId;
use App\Domain\User\ValueObject\Security\RefreshTokenHash;
use App\Domain\User\ValueObject\UserId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RefreshTokenTest extends TestCase
{
    public function testIssueCreatesAnUnexpiredTokenForItsUser(): void
    {
        $now = new DateTimeImmutable('2026-07-18 10:00:00');
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $token = RefreshToken::issue(
            RefreshTokenId::fromString('550e8400-e29b-41d4-a716-446655440001'),
            $userId,
            RefreshTokenHash::fromString('hash'),
            new DateTimeImmutable('2026-07-19 10:00:00'),
            $now,
        );

        $this->assertTrue($token->belongsTo($userId));
        $this->assertFalse($token->isExpired($now));
        $this->assertSame($now, $token->getCreatedAt());
    }

    public function testIssueRejectsAnAlreadyExpiredToken(): void
    {
        $this->expectException(UserDomainException::class);
        $this->expectExceptionMessage('A refresh token must expire in the future.');

        RefreshToken::issue(
            RefreshTokenId::fromString('550e8400-e29b-41d4-a716-446655440001'),
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            RefreshTokenHash::fromString('hash'),
            new DateTimeImmutable('2026-07-18 10:00:00'),
            new DateTimeImmutable('2026-07-18 10:00:00'),
        );
    }
}
