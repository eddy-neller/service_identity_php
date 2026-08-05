<?php

declare(strict_types=1);

namespace App\Infrastructure\Tests\Unit\Notification\User;

use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\Shop\Port\CustomerRepositoryInterface;
use App\Application\User\Port\RefreshTokenRepositoryInterface;
use App\Application\User\Port\TokenProviderInterface;
use App\Application\User\Port\UserNotifierInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Domain\User\Event\Security\ReauthenticationReason;
use App\Domain\User\Event\Security\UserReauthenticationRequiredEvent;
use App\Domain\User\ValueObject\Identity\UserId;
use App\Infrastructure\EventSubscriber\User\UserEventSubscriber;
use App\Infrastructure\Service\Token\AuthVersionStoreInterface;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class UserEventSubscriberTest extends TestCase
{
    private RefreshTokenRepositoryInterface&MockObject $refreshTokenRepository;

    private LoggerInterface&MockObject $logger;

    private AuthVersionStoreInterface&MockObject $authVersionStore;

    private UserEventSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->refreshTokenRepository = $this->createMock(RefreshTokenRepositoryInterface::class);
        $this->authVersionStore = $this->createMock(AuthVersionStoreInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->subscriber = new UserEventSubscriber(
            $this->createStub(UserRepositoryInterface::class),
            $this->createStub(CustomerRepositoryInterface::class),
            $this->createStub(TokenProviderInterface::class),
            $this->createStub(UserNotifierInterface::class),
            $this->refreshTokenRepository,
            $this->authVersionStore,
            $this->logger,
            $this->createStub(CommandBusInterface::class),
        );
    }

    public function testOnUserReauthenticationRequiredRevokesAllRefreshTokens(): void
    {
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $occurredOn = new DateTimeImmutable('2025-01-01 10:00:00');
        $event = new UserReauthenticationRequiredEvent(
            $userId,
            ReauthenticationReason::ACCOUNT_LOCKED,
            $occurredOn,
        );

        $this->logger->expects($this->once())
            ->method('info')
            ->with('User reauthentication required', [
                'user_id' => $userId->toString(),
                'reason' => ReauthenticationReason::ACCOUNT_LOCKED->value,
                'occurred_on' => '2025-01-01 10:00:00',
            ]);

        $this->refreshTokenRepository->expects($this->once())
            ->method('deleteAllForUser')
            ->with($userId);
        $this->authVersionStore->expects($this->once())
            ->method('rotate')
            ->with($userId->toString());

        $this->subscriber->onUserReauthenticationRequired($event);
    }

    public function testOnUserReauthenticationRequiredKeepsSessionsForRoleChanges(): void
    {
        $event = new UserReauthenticationRequiredEvent(
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            ReauthenticationReason::ROLES_CHANGED,
            new DateTimeImmutable('2025-01-01 10:00:00'),
        );

        $this->refreshTokenRepository->expects($this->never())
            ->method('deleteAllForUser');
        $this->authVersionStore->expects($this->never())
            ->method('rotate');
        $this->logger->expects($this->once())
            ->method('info')
            ->with('User reauthentication required', [
                'user_id' => '550e8400-e29b-41d4-a716-446655440000',
                'reason' => ReauthenticationReason::ROLES_CHANGED->value,
                'occurred_on' => '2025-01-01 10:00:00',
            ]);

        $this->subscriber->onUserReauthenticationRequired($event);
    }

    public function testGetSubscribedEventsRegistersReauthenticationListener(): void
    {
        $this->refreshTokenRepository->expects($this->never())
            ->method('deleteAllForUser');
        $this->authVersionStore->expects($this->never())
            ->method('rotate');

        $this->logger->expects($this->never())
            ->method('info');

        $events = UserEventSubscriber::getSubscribedEvents();

        $this->assertSame('onUserReauthenticationRequired', $events['user.reauthentication.required']);
    }
}
