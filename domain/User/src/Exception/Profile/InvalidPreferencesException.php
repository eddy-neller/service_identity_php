<?php

declare(strict_types=1);

namespace App\Domain\User\Exception\Profile;

use App\Domain\SharedKernel\Exception\InvalidArgumentInterface;
use App\Domain\User\Exception\UserDomainException;

final class InvalidPreferencesException extends UserDomainException implements InvalidArgumentInterface
{
    public static function unsupportedLang(string $lang): self
    {
        return new self(sprintf('Unsupported language: %s.', $lang));
    }
}
