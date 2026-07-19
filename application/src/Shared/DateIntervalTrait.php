<?php

declare(strict_types=1);

namespace App\Application\Shared;

use DateInterval;
use Exception;
use RuntimeException;

trait DateIntervalTrait
{
    private function createInterval(string $spec): DateInterval
    {
        try {
            return new DateInterval($spec);
        } catch (Exception $exception) {
            throw new RuntimeException(sprintf('Invalid interval spec "%s"', $spec), 0, $exception);
        }
    }
}
