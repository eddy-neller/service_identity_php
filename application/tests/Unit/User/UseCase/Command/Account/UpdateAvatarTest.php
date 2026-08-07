<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\User\UseCase\Command\Account;

use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\DomainEventBusInterface;
use App\Application\Shared\Port\FileInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\AvatarImageValidatorInterface;
use App\Application\User\Port\AvatarStorageInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Application\User\UseCase\Command\Account\UpdateAvatar\UpdateAvatarCommand;
use App\Application\User\UseCase\Command\Account\UpdateAvatar\UpdateAvatarCommandHandler;
use App\Domain\User\Exception\Profile\InvalidAvatarException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\Model\User;
use App\Domain\User\ValueObject\Identity\EmailAddress;
use App\Domain\User\ValueObject\Identity\UserId;
use App\Domain\User\ValueObject\Identity\Username;
use App\Domain\User\ValueObject\Profile\Preferences;
use App\Domain\User\ValueObject\Security\HashedPassword;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class UpdateAvatarTest extends TestCase
{
    private UserRepositoryInterface&MockObject $repository;

    private AvatarImageValidatorInterface&MockObject $avatarImageValidator;

    private AvatarStorageInterface&MockObject $avatarStorage;

    private ClockInterface&MockObject $clock;

    private TransactionalInterface&MockObject $transactional;

    private DomainEventBusInterface&MockObject $eventBus;

    private UpdateAvatarCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(UserRepositoryInterface::class);
        $this->avatarImageValidator = $this->createMock(AvatarImageValidatorInterface::class);
        $this->avatarStorage = $this->createMock(AvatarStorageInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->transactional = $this->createMock(TransactionalInterface::class);
        $this->eventBus = $this->createMock(DomainEventBusInterface::class);
        $this->handler = new UpdateAvatarCommandHandler(
            $this->repository,
            $this->avatarImageValidator,
            $this->avatarStorage,
            $this->clock,
            $this->transactional,
            $this->eventBus,
        );
    }

    public function testHandleUpdatesAvatarWhenUserExists(): void
    {
        $this->eventBus->expects($this->once())->method('publishAll');

        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $user = $this->createUser($userId);
        $previousAvatar = '0123456789abcdef0123456789abcdef.jpg';
        $user->updateAvatar($previousAvatar, new DateTimeImmutable('2025-01-01 10:00:00'));
        $user->clearDomainEvents();

        $avatarFileName = 'avatar.jpg';
        $file = $this->createStub(FileInterface::class);
        $file->method('isValid')->willReturn(true);
        $file->method('getClientOriginalName')->willReturn($avatarFileName);

        $command = new UpdateAvatarCommand($userId->toString(), $file);

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn($user);

        $this->avatarImageValidator->expects($this->once())
            ->method('validate')
            ->with($file);

        $this->avatarStorage->expects($this->once())
            ->method('store')
            ->with($file)
            ->willReturn($avatarFileName);

        $this->avatarStorage->expects($this->once())
            ->method('delete')
            ->with($previousAvatar);

        $this->clock->expects($this->once())
            ->method('now')
            ->willReturn(new DateTimeImmutable());

        $this->repository->expects($this->once())
            ->method('save')
            ->with($user);

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(function (callable $callback) {
                return $callback();
            });

        $output = $this->handler->handle($command);

        $this->assertSame($userId->toString(), $output->id);
        $this->assertSame($avatarFileName, $output->avatar);
        $this->assertSame($avatarFileName, $user->getAvatarName());
    }

    public function testHandleDeletesStoredAvatarWhenUserIsNotFoundInTransaction(): void
    {
        $this->eventBus->expects($this->never())->method('publishAll');

        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440001');
        $file = $this->createStub(FileInterface::class);
        $file->method('isValid')->willReturn(true);
        $file->method('getClientOriginalName')->willReturn('avatar.jpg');

        $command = new UpdateAvatarCommand($userId->toString(), $file);

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn(null);

        $this->clock->expects($this->never())
            ->method('now');

        $this->avatarImageValidator->expects($this->once())
            ->method('validate');

        $avatar = 'stored-avatar.jpg';

        $this->avatarStorage->expects($this->once())
            ->method('store')
            ->with($file)
            ->willReturn($avatar);

        $this->avatarStorage->expects($this->once())
            ->method('delete')
            ->with($avatar);

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static fn (callable $callback) => $callback());

        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage('User not found.');

        $this->handler->handle($command);
    }

    public function testHandleThrowsExceptionWhenAvatarFileIsInvalid(): void
    {
        $this->eventBus->expects($this->never())->method('publishAll');

        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440002');
        $file = $this->createStub(FileInterface::class);

        $command = new UpdateAvatarCommand($userId->toString(), $file);

        $this->repository->expects($this->never())
            ->method('findById')
            ->with($userId);

        $this->avatarImageValidator->expects($this->once())
            ->method('validate')
            ->with($file)
            ->willThrowException(InvalidAvatarException::invalidMimeType('text/plain'));

        $this->avatarStorage->expects($this->never())
            ->method('store');

        $this->clock->expects($this->never())
            ->method('now');

        $this->transactional->expects($this->never())
            ->method('transactional');

        $this->expectException(InvalidAvatarException::class);
        $this->expectExceptionMessage('Invalid avatar file type: text/plain.');

        $this->handler->handle($command);
    }

    public function testHandleDeletesStoredAvatarWhenTransactionFails(): void
    {
        $this->eventBus->expects($this->never())->method('publishAll');

        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440003');
        $file = $this->createStub(FileInterface::class);
        $file->method('isValid')->willReturn(true);
        $command = new UpdateAvatarCommand($userId->toString(), $file);
        $avatar = 'stored-avatar.jpg';

        $this->repository->expects($this->never())
            ->method('findById')
            ->with($userId);

        $this->avatarImageValidator->expects($this->once())
            ->method('validate')
            ->with($file);

        $this->avatarStorage->expects($this->once())
            ->method('store')
            ->with($file)
            ->willReturn($avatar);

        $this->avatarStorage->expects($this->once())
            ->method('delete')
            ->with($avatar);

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willThrowException(new RuntimeException('Database failure.'));

        $this->repository->expects($this->never())
            ->method('save');

        $this->clock->expects($this->never())
            ->method('now');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Database failure.');

        $this->handler->handle($command);
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
