<?php

declare(strict_types=1);

namespace App\Infrastructure\Service\Token;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final readonly class RedisAuthVersionStore implements AuthVersionStoreInterface
{
    private const string KEY_PREFIX = 'auth-version-';

    public function __construct(
        #[Autowire(service: 'cache.jwt_auth')]
        private CacheInterface&CacheItemPoolInterface $cache,
    ) {
    }

    public function getOrCreate(string $userId): string
    {
        return $this->cache->get(
            self::KEY_PREFIX . $userId,
            static fn (ItemInterface $item): string => self::generate(),
        );
    }

    public function rotate(string $userId): string
    {
        $authVersion = self::generate();
        $item = $this->cache->getItem(self::KEY_PREFIX . $userId);
        $item->set($authVersion);

        $this->cache->save($item);

        return $authVersion;
    }

    public function matches(string $userId, string $authVersion): bool
    {
        $item = $this->cache->getItem(self::KEY_PREFIX . $userId);
        $storedVersion = $item->isHit() ? $item->get() : null;

        return is_string($storedVersion) && hash_equals($storedVersion, $authVersion);
    }

    private static function generate(): string
    {
        return bin2hex(random_bytes(32));
    }
}
