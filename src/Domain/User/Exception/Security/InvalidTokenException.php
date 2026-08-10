<?php

declare(strict_types=1);

namespace App\Domain\User\Exception\Security;

use App\Domain\SharedKernel\Exception\InvalidArgumentInterface;

/**
 * Jeton illisible : ce n'est pas un base64url valide, ou il ne porte pas la forme
 * `email&token`. Distinct d'un jeton bien formé mais inconnu, qui reste une absence
 * d'utilisateur — la confusion entre les deux rendait le diagnostic impossible.
 */
final class InvalidTokenException extends SecurityDomainException implements InvalidArgumentInterface
{
    public function __construct(string $message = 'Invalid token.')
    {
        parent::__construct($message);
    }
}
