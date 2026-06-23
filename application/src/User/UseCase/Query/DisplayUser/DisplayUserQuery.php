<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Query\DisplayUser;

use App\Application\Shared\CQRS\Query\CacheableQueryInterface;
use App\Domain\User\ValueObject\UserId;

final readonly class DisplayUserQuery implements CacheableQueryInterface
{
    private const int CACHE_TTL_SECONDS = 3600;

    public function __construct(
        public UserId $userId,
    ) {
    }

    public function cacheKey(): string
    {
        return 'user:item:' . $this->userId->toString();
    }

    public function cacheTtl(): int
    {
        return self::CACHE_TTL_SECONDS;
    }

    public function cacheTags(): array
    {
        return ['users-collection', 'user-' . $this->userId->toString()];
    }
}
