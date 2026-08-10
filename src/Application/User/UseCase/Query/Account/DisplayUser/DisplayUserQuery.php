<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Query\Account\DisplayUser;

use App\Application\Shared\CQRS\Query\CacheableQueryInterface;

final readonly class DisplayUserQuery implements CacheableQueryInterface
{
    private const int CACHE_TTL_SECONDS = 3600;

    public function __construct(
        public string $userId,
    ) {
    }

    public function cacheKey(): string
    {
        return 'user-item-' . $this->userId;
    }

    public function cacheTtl(): int
    {
        return self::CACHE_TTL_SECONDS;
    }

    public function cacheTags(): array
    {
        return ['users-collection', 'user-' . $this->userId];
    }
}
