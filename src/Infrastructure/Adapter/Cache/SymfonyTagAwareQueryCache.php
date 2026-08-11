<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter\Cache;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * @codeCoverageIgnore
 */
final readonly class SymfonyTagAwareQueryCache implements QueryCacheInterface
{
    public function __construct(
        #[Autowire(service: 'cache.tag')]
        private TagAwareCacheInterface $cache,
    ) {
    }

    public function get(string $key, int $ttlSeconds, array $tags, callable $callback): mixed
    {
        return $this->cache->get($key, function (ItemInterface $item) use ($ttlSeconds, $tags, $callback): mixed {
            $item->expiresAfter($ttlSeconds);
            if ([] !== $tags) {
                $item->tag($tags);
            }

            return $callback();
        });
    }

    public function invalidateTags(array $tags): void
    {
        if ([] === $tags) {
            return;
        }

        $this->cache->invalidateTags($tags);
    }
}
