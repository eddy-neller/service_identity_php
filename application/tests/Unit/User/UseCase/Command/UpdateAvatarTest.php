<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\User\UseCase\Command;

use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\FileInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\AvatarUploaderInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Application\User\UseCase\Command\UpdateAvatar\UpdateAvatarCommand;
use App\Application\User\UseCase\Command\UpdateAvatar\UpdateAvatarCommandHandler;
use App\Domain\User\Exception\UserDomainException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\Model\User;
use App\Domain\User\ValueObject\Avatar;
use App\Domain\User\ValueObject\EmailAddress;
use App\Domain\User\ValueObject\Preferences;
use App\Domain\User\ValueObject\Security\HashedPassword;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\Username;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UpdateAvatarTest extends TestCase
{
    private UserRepositoryInterface&MockObject $repository;

    private AvatarUploaderInterface&MockObject $avatarUploader;

    private ClockInterface&MockObject $clock;

    private TransactionalInterface&MockObject $transactional;

    private UpdateAvatarCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(UserRepositoryInterface::class);
        $this->avatarUploader = $this->createMock(AvatarUploaderInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->transactional = $this->createMock(TransactionalInterface::class);
        $this->handler = new UpdateAvatarCommandHandler(
            $this->repository,
            $this->avatarUploader,
            $this->clock,
            $this->transactional,
        );
    }

    public function testHandleUpdatesAvatarWhenUserExists(): void
    {
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $user = $this->createUser($userId);
        $avatarFileName = 'avatar.jpg';
        $file = $this->createStub(FileInterface::class);
        $file->method('isValid')->willReturn(true);
        $file->method('getClientOriginalName')->willReturn($avatarFileName);

        $command = new UpdateAvatarCommand($userId->toString(), $file);

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn($user);

        $this->avatarUploader->expects($this->once())
            ->method('upload')
            ->with($userId, $file)
            ->willReturn(new Avatar($avatarFileName));

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
        $this->assertSame($avatarFileName, $output->avatar->fileName());
        $this->assertSame($avatarFileName, $user->getAvatar()->fileName());
    }

    public function testHandleThrowsExceptionWhenUserNotFound(): void
    {
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

        $this->avatarUploader->expects($this->never())
            ->method('upload');

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static fn (callable $callback) => $callback());

        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage('User not found.');

        $this->handler->handle($command);
    }

    public function testHandleThrowsExceptionWhenAvatarFileIsInvalid(): void
    {
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440002');
        $file = $this->createStub(FileInterface::class);
        $file->method('isValid')->willReturn(false);

        $command = new UpdateAvatarCommand($userId->toString(), $file);

        $this->repository->expects($this->never())
            ->method('findById');

        $this->avatarUploader->expects($this->never())
            ->method('upload');

        $this->clock->expects($this->never())
            ->method('now');

        $this->transactional->expects($this->never())
            ->method('transactional');

        $this->expectException(UserDomainException::class);
        $this->expectExceptionMessage('Fichier avatar invalide.');

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
