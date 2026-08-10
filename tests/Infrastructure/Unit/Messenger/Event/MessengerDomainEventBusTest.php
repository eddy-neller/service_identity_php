<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Unit\Messenger\Event;

use App\Domain\User\Event\Lifecycle\UserRegisteredEvent;
use App\Domain\User\ValueObject\Identity\UserId;
use App\Infrastructure\Messenger\Event\MessengerDomainEventBus;
use App\Infrastructure\Messenger\Event\PublishedDomainEventCollector;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class MessengerDomainEventBusTest extends TestCase
{
    private MessageBusInterface&MockObject $bus;

    private MessengerDomainEventBus $eventBus;

    private PublishedDomainEventCollector $collector;

    protected function setUp(): void
    {
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->collector = new PublishedDomainEventCollector();
        $this->eventBus = new MessengerDomainEventBus($this->bus, $this->collector);
    }

    public function testPublishAllDispatchesEveryEvent(): void
    {
        $first = $this->createEvent();
        $second = $this->createEvent();
        $dispatched = [];

        $this->bus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$dispatched): Envelope {
                $dispatched[] = $message;

                return new Envelope($message);
            });

        $this->eventBus->publishAll([$first, $second]);

        $this->assertSame([$first, $second], $dispatched);
    }

    public function testPublishAllDoesNothingWithoutEvents(): void
    {
        $this->bus->expects($this->never())->method('dispatch');

        $this->eventBus->publishAll([]);

        $this->assertSame([], $this->collector->release());
    }

    /**
     * Les événements publiés sont offerts au collecteur : c'est ce qui permet au
     * CacheInvalidationMiddleware de purger les tags avant la réponse HTTP, sans
     * attendre le worker qui consomme l'outbox.
     */
    public function testPublishAllRecordsEventsForSynchronousCacheInvalidation(): void
    {
        $first = $this->createEvent();
        $second = $this->createEvent();

        $this->bus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $this->eventBus->publishAll([$first, $second]);

        $this->assertSame([$first, $second], $this->collector->release());
    }

    private function createEvent(): UserRegisteredEvent
    {
        return new UserRegisteredEvent(
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            new DateTimeImmutable('2026-08-06 12:00:00'),
        );
    }
}
