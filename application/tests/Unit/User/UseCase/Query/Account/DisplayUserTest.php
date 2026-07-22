<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\User\UseCase\Query\Account;

use App\Application\User\Port\UserRepositoryInterface;
use App\Application\User\ReadModel\UserItem;
use App\Application\User\UseCase\Query\Account\DisplayUser\DisplayUserQuery;
use App\Application\User\UseCase\Query\Account\DisplayUser\DisplayUserQueryHandler;
use App\Domain\User\Exception\UserDomainException;
use App\Domain\User\Model\User;
use App\Domain\User\ValueObject\EmailAddress;
use App\Domain\User\ValueObject\Preferences;
use App\Domain\User\ValueObject\Security\HashedPassword;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\Username;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DisplayUserTest extends TestCase
{
    private UserRepositoryInterface&MockObject $repository;

    private DisplayUserQueryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(UserRepositoryInterface::class);
        $this->handler = new DisplayUserQueryHandler($this->repository);
    }

    public function testHandleReturnsUserOutputWhenUserExists(): void
    {
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $query = new DisplayUserQuery($userId->toString());
        $user = $this->createUser($userId);

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn($user);

        $output = $this->handler->handle($query);

        $this->assertInstanceOf(UserItem::class, $output);
        $this->assertSame($userId->toString(), $output->id);
        $this->assertSame('testuser', $output->username);
        $this->assertSame('test@example.com', $output->email);
    }

    public function testHandleThrowsExceptionWhenUserNotFound(): void
    {
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440001');
        $query = new DisplayUserQuery($userId->toString());

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn(null);

        $this->expectException(UserDomainException::class);
        $this->expectExceptionMessage('User not found.');

        $this->handler->handle($query);
    }

    public function testQueryCacheKeyAndTagsUseUserId(): void
    {
        $this->repository->expects($this->never())->method('findById');

        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440002');
        $query = new DisplayUserQuery($userId->toString());

        $this->assertSame('user:item:550e8400-e29b-41d4-a716-446655440002', $query->cacheKey());
        $this->assertSame(
            ['users-collection', 'user-550e8400-e29b-41d4-a716-446655440002'],
            $query->cacheTags(),
        );
    }

    public function testQueryCacheTtl(): void
    {
        $this->repository->expects($this->never())->method('findById');

        $query = new DisplayUserQuery('550e8400-e29b-41d4-a716-446655440003');

        $this->assertSame(3600, $query->cacheTtl());
    }

    private function createUser(UserId $userId): User
    {
        return User::register(
            id: $userId,
            username: Username::fromString('testuser'),
            email: EmailAddress::fromString('test@example.com'),
            password: HashedPassword::fromString('hash'),
            preferences: Preferences::fromArray(['lang' => 'fr']),
            now: new DateTimeImmutable(),
        );
    }
}
