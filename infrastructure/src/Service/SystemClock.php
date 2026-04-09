<?php

declare(strict_types=1);

namespace App\Infrastructure\Service;

use App\Application\Shared\Port\ClockInterface;
use DateTimeImmutable;

final class SystemClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
