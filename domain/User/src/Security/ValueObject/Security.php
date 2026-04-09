<?php

declare(strict_types=1);

namespace App\Domain\User\Security\ValueObject;

use JsonSerializable;

final readonly class Security implements JsonSerializable
{
    public function __construct(
        private int $totalWrongPassword = 0,
        private int $totalWrongTwoFactorCode = 0,
        private int $totalTwoFactorSmsSent = 0,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            totalWrongPassword: (int) ($data['totalWrongPassword'] ?? 0),
            totalWrongTwoFactorCode: (int) ($data['totalWrongTwoFactorCode'] ?? 0),
            totalTwoFactorSmsSent: (int) ($data['totalTwoFactorSmsSent'] ?? 0),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'totalWrongPassword' => $this->totalWrongPassword,
            'totalWrongTwoFactorCode' => $this->totalWrongTwoFactorCode,
            'totalTwoFactorSmsSent' => $this->totalTwoFactorSmsSent,
        ];
    }

    public function toArray(): array
    {
        return $this->jsonSerialize();
    }

    public function getTotalWrongPassword(): int
    {
        return $this->totalWrongPassword;
    }

    public function getTotalWrongTwoFactorCode(): int
    {
        return $this->totalWrongTwoFactorCode;
    }

    public function getTotalTwoFactorSmsSent(): int
    {
        return $this->totalTwoFactorSmsSent;
    }

    public function withTotalWrongPassword(int $totalWrongPassword): self
    {
        return new self(
            totalWrongPassword: $totalWrongPassword,
            totalWrongTwoFactorCode: $this->totalWrongTwoFactorCode,
            totalTwoFactorSmsSent: $this->totalTwoFactorSmsSent,
        );
    }

    public function withTotalWrongTwoFactorCode(int $totalWrongTwoFactorCode): self
    {
        return new self(
            totalWrongPassword: $this->totalWrongPassword,
            totalWrongTwoFactorCode: $totalWrongTwoFactorCode,
            totalTwoFactorSmsSent: $this->totalTwoFactorSmsSent,
        );
    }

    public function withTotalTwoFactorSmsSent(int $totalTwoFactorSmsSent): self
    {
        return new self(
            totalWrongPassword: $this->totalWrongPassword,
            totalWrongTwoFactorCode: $this->totalWrongTwoFactorCode,
            totalTwoFactorSmsSent: $totalTwoFactorSmsSent,
        );
    }
}
