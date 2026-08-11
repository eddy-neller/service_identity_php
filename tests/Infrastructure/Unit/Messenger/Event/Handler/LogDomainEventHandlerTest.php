<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Unit\Messenger\Event\Handler;

use App\Domain\User\Event\Lifecycle\ActivationEmailRequestedEvent;
use App\Domain\User\Event\Lifecycle\UserRegisteredEvent;
use App\Domain\User\ValueObject\Identity\EmailAddress;
use App\Domain\User\ValueObject\Identity\UserId;
use App\Infrastructure\Symfony\Messenger\Event\Handler\LogDomainEventHandler;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class LogDomainEventHandlerTest extends TestCase
{
    private const string USER_ID = '550e8400-e29b-41d4-a716-446655440000';

    public function testItLogsNameIdentityAggregateAndDate(): void
    {
        $event = $this->event();

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('Domain event handled', [
                'event' => 'user.registered',
                'event_id' => $event->eventId(),
                'aggregate_id' => self::USER_ID,
                'occurred_on' => '2026-08-06 12:00:00',
            ]);

        (new LogDomainEventHandler($logger))($event);
    }

    /**
     * L'adresse e-mail portée par un événement ne doit jamais atterrir dans le contexte du
     * journal : `aggregate_id` suffit à corréler, sans écrire de donnée personnelle.
     */
    public function testItKeepsPersonalDataOutOfTheLogContext(): void
    {
        $event = new ActivationEmailRequestedEvent(
            UserId::fromString(self::USER_ID),
            EmailAddress::fromString('john@example.com'),
            new DateTimeImmutable('2026-08-06 12:00:00'),
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                $this->anything(),
                $this->callback(static fn (array $context): bool => !in_array('john@example.com', $context, true)),
            );

        (new LogDomainEventHandler($logger))($event);
    }

    private function event(): UserRegisteredEvent
    {
        return new UserRegisteredEvent(
            UserId::fromString(self::USER_ID),
            new DateTimeImmutable('2026-08-06 12:00:00'),
        );
    }
}
