<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Unit\Command\CQRS\Middleware;

use App\Application\Shared\CQRS\Command\CommandInterface;
use App\Domain\User\Event\Lifecycle\UserRegisteredEvent;
use App\Domain\User\Event\Security\UserPasswordUpdatedEvent;
use App\Domain\User\ValueObject\Identity\UserId;
use App\Infrastructure\Messenger\CQRS\Middleware\CacheInvalidationMiddleware;
use App\Infrastructure\Messenger\Event\PublishedDomainEventCollector;
use App\Infrastructure\Service\Cache\DomainEventCacheTags;
use App\Infrastructure\Service\Cache\QueryCacheInterface;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final class CacheInvalidationMiddlewareTest extends TestCase
{
    private const string USER_ID = '550e8400-e29b-41d4-a716-446655440000';

    private PublishedDomainEventCollector $collector;

    private QueryCacheInterface&MockObject $cache;

    protected function setUp(): void
    {
        $this->collector = new PublishedDomainEventCollector();
        $this->cache = $this->createMock(QueryCacheInterface::class);
    }

    public function testHandleInvalidatesTheTagsOfTheEventsPublishedByTheCommand(): void
    {
        $this->cache->expects($this->once())
            ->method('invalidateTags')
            ->with(['users-collection', 'user-' . self::USER_ID]);

        $this->handle(fn () => $this->collector->record($this->registeredEvent()));
    }

    /**
     * L'invalidation doit intervenir *après* le handler : celui-ci ouvre et committe sa
     * transaction, purger avant le commit laisserait un lecteur concurrent recacher
     * l'état d'avant l'écriture.
     */
    public function testHandleInvalidatesAfterTheHandlerHasRun(): void
    {
        $sequence = [];

        $this->cache->expects($this->once())
            ->method('invalidateTags')
            ->willReturnCallback(static function () use (&$sequence): void {
                $sequence[] = 'invalidate';
            });

        $this->handle(function () use (&$sequence): void {
            $sequence[] = 'handler';
            $this->collector->record($this->registeredEvent());
        });

        self::assertSame(['handler', 'invalidate'], $sequence);
    }

    public function testHandleDeduplicatesTagsAcrossEvents(): void
    {
        $this->cache->expects($this->once())
            ->method('invalidateTags')
            ->with(['users-collection', 'user-' . self::USER_ID]);

        $this->handle(function (): void {
            $this->collector->record($this->registeredEvent());
            $this->collector->record(new UserPasswordUpdatedEvent(
                UserId::fromString(self::USER_ID),
                new DateTimeImmutable('2026-08-06 12:00:00'),
            ));
        });
    }

    public function testHandleDoesNotTouchTheCacheWithoutEvents(): void
    {
        $this->cache->expects($this->never())->method('invalidateTags');

        $this->handle(static function (): void {
        });
    }

    /**
     * Un worker traite les messages en série : le collecteur doit être vidé même quand la
     * commande lève, sinon les événements fuient vers le message suivant.
     */
    public function testHandleDrainsTheCollectorWhenTheCommandFails(): void
    {
        $exception = new RuntimeException('Handler failure');
        $this->collector->record($this->registeredEvent());

        $this->cache->expects($this->once())->method('invalidateTags');

        try {
            $this->handle(static function () use ($exception): void {
                throw $exception;
            });
            self::fail('The command failure should have been propagated.');
        } catch (RuntimeException $caught) {
            self::assertSame($exception, $caught);
        }

        self::assertSame([], $this->collector->release());
    }

    private function handle(callable $handler): Envelope
    {
        $envelope = new Envelope(new class implements CommandInterface {
        });

        $next = $this->createMock(MiddlewareInterface::class);
        $next->expects($this->once())
            ->method('handle')
            ->willReturnCallback(static function (Envelope $envelope) use ($handler): Envelope {
                $handler();

                return $envelope;
            });

        $stack = $this->createMock(StackInterface::class);
        $stack->expects($this->once())->method('next')->willReturn($next);

        return (new CacheInvalidationMiddleware($this->collector, new DomainEventCacheTags(), $this->cache))
            ->handle($envelope, $stack);
    }

    private function registeredEvent(): UserRegisteredEvent
    {
        return new UserRegisteredEvent(
            UserId::fromString(self::USER_ID),
            new DateTimeImmutable('2026-08-06 12:00:00'),
        );
    }
}
