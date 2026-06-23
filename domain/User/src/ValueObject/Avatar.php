<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObject;

final readonly class Avatar
{
    public function __construct(
        private ?string $fileName = null,
    ) {
    }

    public function fileName(): ?string
    {
        return $this->fileName;
    }

    public function withFile(?string $fileName): self
    {
        return new self(
            fileName: $fileName,
        );
    }
}
