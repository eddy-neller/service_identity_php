<?php

declare(strict_types=1);

namespace App\Domain\Shop\Customer\Exception;

use Throwable;

final class AddressLimitReachedException extends CustomerDomainException
{
    public function __construct(
        string $message = 'A customer cannot have more than 5 addresses.',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
