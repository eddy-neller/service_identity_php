<?php

declare(strict_types=1);

namespace App\Domain\Shop\Ordering\Exception;

use App\Domain\SharedKernel\Exception\InvalidArgumentInterface;

final class InvalidOrderLineQuantityException extends CartDomainException implements InvalidArgumentInterface
{
    public static function notPositive(): self
    {
        return new self('Order line quantity must be greater than zero.');
    }
}
