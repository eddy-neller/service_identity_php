<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObject\Security;

use JsonSerializable;

final readonly class ResetPassword implements JsonSerializable
{
    private function __construct(
        private int $mailSent = 0,
        private ?string $token = null,
        private ?int $tokenTtl = null,
    ) {
    }

    public static function create(
        int $mailSent = 0,
        ?string $token = null,
        ?int $tokenTtl = null,
    ): self {
        return new self(
            mailSent: $mailSent,
            token: $token,
            tokenTtl: $tokenTtl,
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            mailSent: (int) ($data['mailSent'] ?? 0),
            token: $data['token'] ?? null,
            tokenTtl: $data['tokenTtl'] ?? null,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'mailSent' => $this->mailSent,
            'token' => $this->token,
            'tokenTtl' => $this->tokenTtl,
        ];
    }

    public function toArray(): array
    {
        return $this->jsonSerialize();
    }

    public function getMailSent(): int
    {
        return $this->mailSent;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function getTokenTtl(): ?int
    {
        return $this->tokenTtl;
    }

    public function withMailSent(int $mailSent): self
    {
        return new self(
            mailSent: $mailSent,
            token: $this->token,
            tokenTtl: $this->tokenTtl,
        );
    }

    public function withToken(?string $token): self
    {
        return new self(
            mailSent: $this->mailSent,
            token: $token,
            tokenTtl: $this->tokenTtl,
        );
    }

    public function withTokenTtl(?int $tokenTtl): self
    {
        return new self(
            mailSent: $this->mailSent,
            token: $this->token,
            tokenTtl: $tokenTtl,
        );
    }
}
