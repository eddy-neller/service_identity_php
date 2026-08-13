<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Unit\Symfony\Messenger\CQRS\Middleware;

use App\Application\Shared\CQRS\Query\CacheableQueryInterface;
use App\Application\Shared\CQRS\Query\QueryInterface;
use App\Infrastructure\Adapter\Cache\QueryCacheInterface;
use App\Infrastructure\Symfony\Messenger\CQRS\HandledResultExtractor;
use App\Infrastructure\Symfony\Messenger\CQRS\Middleware\QueryCacheMiddleware;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final class QueryCacheMiddlewareTest extends TestCase
{
    public function testHandleBypassesCacheForANonCacheableQuery(): void
    {
        $query = new class implements QueryInterface {
        };
        $envelope = new Envelope($query);
        $handledEnvelope = $envelope->with(new HandledStamp('result', 'handler'));
        $cache = $this->createMock(QueryCacheInterface::class);
        $cache->expects($this->never())->method('get');
        $stack = $this->stackReturning($envelope, $handledEnvelope);

        $result = (new QueryCacheMiddleware($cache, new HandledResultExtractor()))
            ->handle($envelope, $stack);

        self::assertSame($handledEnvelope, $result);
    }

    public function testHandleCachesTheHandlerResultOnMiss(): void
    {
        $query = $this->cacheableQuery();
        $envelope = new Envelope($query);
        $handledEnvelope = $envelope->with(new HandledStamp('fresh result', 'handler'));
        $cache = $this->createMock(QueryCacheInterface::class);
        $cache->expects($this->once())
            ->method('get')
            ->with('query-key', 60, ['query-tag'], self::callback(is_callable(...)))
            ->willReturnCallback(static fn (string $key, int $ttl, array $tags, callable $callback): mixed => $callback());
        $stack = $this->stackReturning($envelope, $handledEnvelope);

        $result = (new QueryCacheMiddleware($cache, new HandledResultExtractor()))
            ->handle($envelope, $stack);

        self::assertSame($handledEnvelope, $result);
    }

    public function testHandleReturnsCachedResultWithoutCallingHandler(): void
    {
        $query = $this->cacheableQuery();
        $envelope = new Envelope($query);
        $cache = $this->createMock(QueryCacheInterface::class);
        $cache->expects($this->once())->method('get')->willReturn('cached result');
        $stack = $this->createMock(StackInterface::class);
        $stack->expects($this->never())->method('next');

        $result = (new QueryCacheMiddleware($cache, new HandledResultExtractor()))
            ->handle($envelope, $stack);

        $handledStamp = $result->last(HandledStamp::class);
        self::assertInstanceOf(HandledStamp::class, $handledStamp);
        self::assertSame('cached result', $handledStamp->getResult());
        self::assertSame('query_cache', $handledStamp->getHandlerName());
    }

    public function testHandleSupportsCachedNullResult(): void
    {
        $query = $this->cacheableQuery();
        $envelope = new Envelope($query);
        $cache = $this->createMock(QueryCacheInterface::class);
        $cache->expects($this->once())->method('get')->willReturn(null);
        $stack = $this->createMock(StackInterface::class);
        $stack->expects($this->never())->method('next');

        $result = (new QueryCacheMiddleware($cache, new HandledResultExtractor()))
            ->handle($envelope, $stack);

        $handledStamp = $result->last(HandledStamp::class);
        self::assertInstanceOf(HandledStamp::class, $handledStamp);
        self::assertNull($handledStamp->getResult());
    }

    public function testHandlePropagatesHandlerFailure(): void
    {
        $query = $this->cacheableQuery();
        $envelope = new Envelope($query);
        $exception = new RuntimeException('Handler failure');
        $cache = $this->createMock(QueryCacheInterface::class);
        $cache->expects($this->once())
            ->method('get')
            ->willReturnCallback(static fn (string $key, int $ttl, array $tags, callable $callback): mixed => $callback());
        $next = $this->createMock(MiddlewareInterface::class);
        $next->expects($this->once())->method('handle')->willThrowException($exception);
        $stack = $this->createMock(StackInterface::class);
        $stack->expects($this->once())->method('next')->willReturn($next);

        $this->expectExceptionObject($exception);

        (new QueryCacheMiddleware($cache, new HandledResultExtractor()))
            ->handle($envelope, $stack);
    }

    private function cacheableQuery(): CacheableQueryInterface
    {
        return new class implements CacheableQueryInterface {
            public function cacheKey(): string
            {
                return 'query-key';
            }

            public function cacheTtl(): int
            {
                return 60;
            }

            public function cacheTags(): array
            {
                return ['query-tag'];
            }
        };
    }

    private function stackReturning(Envelope $envelope, Envelope $handledEnvelope): StackInterface
    {
        $next = $this->createMock(MiddlewareInterface::class);
        $next->expects($this->once())
            ->method('handle')
            ->with($envelope)
            ->willReturn($handledEnvelope);

        $stack = $this->createMock(StackInterface::class);
        $stack->expects($this->once())
            ->method('next')
            ->willReturn($next);

        return $stack;
    }
}
