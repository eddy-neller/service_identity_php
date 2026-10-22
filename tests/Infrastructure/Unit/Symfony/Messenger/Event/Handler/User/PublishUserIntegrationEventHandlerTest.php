<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Unit\Symfony\Messenger\Event\Handler\User;

use App\Domain\User\Event\Lifecycle\UserRegisteredEvent;
use App\Domain\User\Event\Management\UserCreatedByAdminEvent;
use App\Domain\User\Event\Management\UserDeletedByAdminEvent;
use App\Domain\User\Event\UserDomainEventInterface;
use App\Domain\User\ValueObject\Identity\UserId;
use App\Infrastructure\Messaging\UserIntegrationEvent;
use App\Infrastructure\Messaging\UserIntegrationEventPublisherInterface;
use App\Infrastructure\Symfony\Messenger\Event\DomainEventLedgerInterface;
use App\Infrastructure\Symfony\Messenger\Event\Handler\User\PublishUserIntegrationEventHandler;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PublishUserIntegrationEventHandlerTest extends TestCase
{
    private const string USER_ID = '550e8400-e29b-41d4-a716-446655440000';

    private const string OCCURRED_ON = '2026-08-06 12:00:00';

    private UserIntegrationEventPublisherInterface&MockObject $publisher;

    private DomainEventLedgerInterface&MockObject $ledger;

    /** Vrai des que le fait est annonce : voir `testItMarksTheEventAfterPublishing`. */
    private bool $published = false;

    private PublishUserIntegrationEventHandler $handler;

    protected function setUp(): void
    {
        $this->publisher = $this->createMock(UserIntegrationEventPublisherInterface::class);
        $this->ledger = $this->createMock(DomainEventLedgerInterface::class);
        $this->published = false;

        $this->handler = new PublishUserIntegrationEventHandler($this->publisher, $this->ledger);
    }

    /**
     * Les trois faits publics de ce service, et le type du contrat qui leur correspond. Le
     * `userAccountId` publie est l'identifiant du compte : ce service n'annonce rien d'autre.
     */
    #[DataProvider('publishedFacts')]
    public function testItAnnouncesTheFactWithItsContractType(
        string $method,
        UserDomainEventInterface $event,
        string $expectedType,
    ): void {
        $this->ledger->method('hasProcessed')->willReturn(false);
        $this->ledger->expects($this->once())->method('markProcessed')
            ->with($event->eventId(), PublishUserIntegrationEventHandler::class);

        $announced = null;
        $this->publisher->expects($this->once())->method('publish')
            ->willReturnCallback(function (UserIntegrationEvent $given) use (&$announced): void {
                $announced = $given;
            });

        $this->handler->{$method}($event);

        self::assertInstanceOf(UserIntegrationEvent::class, $announced);
        self::assertSame($expectedType, $announced->type);
        self::assertSame($event->eventId(), $announced->eventId);
        self::assertSame(self::USER_ID, $announced->userAccountId);
        self::assertSame(self::OCCURRED_ON, $announced->occurredAt->format('Y-m-d H:i:s'));
    }

    /**
     * @return iterable<string, array{string, UserDomainEventInterface, string}>
     */
    public static function publishedFacts(): iterable
    {
        $userId = UserId::fromString(self::USER_ID);
        $occurredOn = new DateTimeImmutable(self::OCCURRED_ON);

        yield 'inscription' => [
            'onUserRegistered',
            new UserRegisteredEvent($userId, $occurredOn),
            'identity.user.registered',
        ];

        yield 'creation admin' => [
            'onUserCreatedByAdmin',
            new UserCreatedByAdminEvent($userId, $occurredOn),
            'identity.user.created_by_admin',
        ];

        yield 'suppression admin' => [
            'onUserDeletedByAdmin',
            new UserDeletedByAdminEvent($userId, $occurredOn),
            'identity.user.deleted',
        ];
    }

    /**
     * **L'ordre porte la garantie.** Marquer avant de publier echangerait un doublon au rejeu —
     * que le consommateur deduplique — contre une perte definitive que personne ne rattrape.
     */
    public function testItMarksTheEventAfterPublishing(): void
    {
        $event = $this->registeredEvent();
        $this->ledger->method('hasProcessed')->willReturn(false);

        $this->publisher->expects($this->once())->method('publish')
            ->willReturnCallback(function (): void {
                $this->published = true;
            });

        $this->ledger->expects($this->once())->method('markProcessed')
            ->with($event->eventId(), PublishUserIntegrationEventHandler::class)
            ->willReturnCallback(function (): void {
                self::assertTrue($this->published, 'Le marquage doit suivre la publication.');
            });

        $this->handler->onUserRegistered($event);
    }

    /** Redelivrance depuis l'outbox : un fait deja annonce ne doit pas l'etre deux fois. */
    public function testItSkipsAnEventItHasAlreadyPublished(): void
    {
        $event = $this->registeredEvent();

        $this->ledger->expects($this->once())->method('hasProcessed')
            ->with($event->eventId(), PublishUserIntegrationEventHandler::class)
            ->willReturn(true);
        $this->publisher->expects($this->never())->method('publish');
        $this->ledger->expects($this->never())->method('markProcessed');

        $this->handler->onUserRegistered($event);
    }

    /**
     * Si la publication echoue, rien n'est marque : Messenger rejoue le message depuis l'outbox,
     * puis l'envoie en file d'echec au bout de six essais. Un fait annonce a tort serait, lui,
     * definitivement perdu.
     */
    public function testItMarksNothingWhenThePublicationFails(): void
    {
        $this->ledger->method('hasProcessed')->willReturn(false);
        $this->publisher->expects($this->once())->method('publish')
            ->willThrowException(new RuntimeException('sns indisponible'));
        $this->ledger->expects($this->never())->method('markProcessed');

        $this->expectException(RuntimeException::class);

        $this->handler->onUserRegistered($this->registeredEvent());
    }

    private function registeredEvent(): UserRegisteredEvent
    {
        return new UserRegisteredEvent(
            UserId::fromString(self::USER_ID),
            new DateTimeImmutable(self::OCCURRED_ON),
        );
    }
}
