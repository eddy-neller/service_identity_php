<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\User\UseCase\Command;

use App\Domain\User\Model\User;
use App\Domain\User\ValueObject\EmailAddress;
use App\Domain\User\ValueObject\Preferences;
use App\Domain\User\ValueObject\Security\HashedPassword;
use App\Domain\User\ValueObject\Security\RoleSet;
use App\Domain\User\ValueObject\Security\UserStatus;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\Username;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class LoginTest extends TestCase
{
    public function testUserCanRecordASuccessfulLogin(): void
    {
        $user = User::createByAdmin(
            id: UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            username: Username::fromString('john'),
            email: EmailAddress::fromString('john@example.com'),
            password: HashedPassword::fromString('hash'),
            roles: RoleSet::fromArray(['ROLE_USER']),
            status: UserStatus::active(),
            now: new DateTimeImmutable('2026-07-18 10:00:00'),
            preferences: new Preferences(),
        );

        $user->recordSuccessfulLogin(new DateTimeImmutable('2026-07-18 11:00:00'));

        $this->assertSame(1, $user->getLoginCount());
    }
}
