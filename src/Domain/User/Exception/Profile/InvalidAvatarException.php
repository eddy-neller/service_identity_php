<?php

declare(strict_types=1);

namespace App\Domain\User\Exception\Profile;

use App\Domain\SharedKernel\Exception\InvalidArgumentInterface;

final class InvalidAvatarException extends ProfileDomainException implements InvalidArgumentInterface
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

    public static function unreadable(): self
    {
        return new self('Avatar file is not a readable image.');
    }

    public static function invalidDimensions(int $minDimension, int $maxDimension): self
    {
        return new self(sprintf(
            'Avatar dimensions must be between %d and %d pixels.',
            $minDimension,
            $maxDimension,
        ));
    }
}
