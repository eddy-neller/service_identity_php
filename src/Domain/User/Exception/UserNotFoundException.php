<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

use App\Domain\SharedKernel\Exception\EntityNotFoundInterface;
use Throwable;

final class UserNotFoundException extends UserDomainException implements EntityNotFoundInterface
{
    public function __construct(
        string $message = 'User not found.',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
