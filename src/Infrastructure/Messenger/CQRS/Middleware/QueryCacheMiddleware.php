<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger\CQRS\Middleware;

use App\Application\Shared\CQRS\Query\CacheableQueryInterface;
use App\Infrastructure\Messenger\CQRS\HandledResultExtractor;
use App\Infrastructure\Service\Cache\QueryCacheInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class QueryCacheMiddleware implements MiddlewareInterface
{
    private const string CACHE_HANDLER_NAME = 'query_cache';

    public function __construct(
        private QueryCacheInterface $cache,
        private HandledResultExtractor $resultExtractor,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $query = $envelope->getMessage();

        if (!$query instanceof CacheableQueryInterface) {
            return $stack->next()->handle($envelope, $stack);
        }

        $handledEnvelope = null;
        $result = $this->cache->get(
            key: $query->cacheKey(),
            ttlSeconds: $query->cacheTtl(),
            tags: $query->cacheTags(),
            callback: function () use ($envelope, $stack, &$handledEnvelope): mixed {
                $handledEnvelope = $stack->next()->handle($envelope, $stack);

                return $this->resultExtractor->extract($handledEnvelope);
            },
        );

        if ($handledEnvelope instanceof Envelope) {
            return $handledEnvelope;
        }

        return $envelope->with(new HandledStamp($result, self::CACHE_HANDLER_NAME));
    }
}
