<?php

declare(strict_types=1);

namespace App\Infrastructure\Service\Uuid;

interface UuidGeneratorInterface
{
    public function generate(): string;
}
