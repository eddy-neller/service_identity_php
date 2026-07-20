<?php

declare(strict_types=1);

namespace App\Domain\User\Exception\Profile;

use App\Domain\SharedKernel\Exception\InvalidArgumentInterface;
use App\Domain\User\Exception\UserDomainException;

final class InvalidAvatarException extends UserDomainException implements InvalidArgumentInterface
{
    public static function missing(): self
    {
        return new self('No avatar file provided.');
    }

    public static function invalidMimeType(string $mimeType): self
    {
        return new self(sprintf('Invalid avatar file type: %s.', $mimeType));
    }

    public static function tooLarge(int $maxSize): self
    {
        return new self(sprintf('Avatar file exceeds the maximum allowed size (%d bytes).', $maxSize));
    }

    public static function invalidDimensions(int $maxDimension): self
    {
        return new self(sprintf(
            'Avatar dimensions exceed the maximum allowed (%dx%d).',
            $maxDimension,
            $maxDimension,
        ));
    }
}
