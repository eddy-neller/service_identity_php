<?php

declare(strict_types=1);

namespace App\Domain\Shop\Customer\Exception;

use Throwable;

final class AddressNotFoundException extends CustomerDomainException
{
    public function __construct(
        string $message = 'Address not found.',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
