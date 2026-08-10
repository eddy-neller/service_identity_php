<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObject\Identity;

use DateTime;
use DateTimeInterface;
use Exception;
use JsonSerializable;
use UnexpectedValueException;

final readonly class ActiveEmail implements JsonSerializable
{
    private function __construct(
        private int $mailSent = 0,
        private ?string $token = null,
        private ?int $tokenTtl = null,
        private ?DateTimeInterface $lastAttempt = null,
    ) {
    }

    public static function create(
        int $mailSent = 0,
        ?string $token = null,
        ?int $tokenTtl = null,
        ?DateTimeInterface $lastAttempt = null,
    ): self {
        return new self(
            mailSent: $mailSent,
            token: $token,
            tokenTtl: $tokenTtl,
            lastAttempt: $lastAttempt,
        );
    }

    public static function fromArray(array $data): self
    {
        $lastAttempt = null;
        if (isset($data['lastAttempt'])) {
            try {
                $lastAttempt = $data['lastAttempt'] instanceof DateTimeInterface
                    ? $data['lastAttempt']
                    : new DateTime($data['lastAttempt']);
            } catch (Exception $exception) {
                throw new UnexpectedValueException('Unable to parse the "lastAttempt" date value.', previous: $exception);
            }
        }

        return new self(
            mailSent: (int) ($data['mailSent'] ?? 0),
            token: $data['token'] ?? null,
            tokenTtl: $data['tokenTtl'] ?? null,
            lastAttempt: $lastAttempt,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'mailSent' => $this->mailSent,
            'token' => $this->token,
            'tokenTtl' => $this->tokenTtl,
            'lastAttempt' => $this->lastAttempt?->format('c'),
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

    public function getLastAttempt(): ?DateTimeInterface
    {
        return $this->lastAttempt;
    }

    public function withMailSent(int $mailSent): self
    {
        return new self(
            mailSent: $mailSent,
            token: $this->token,
            tokenTtl: $this->tokenTtl,
            lastAttempt: $this->lastAttempt,
        );
    }

    public function withToken(?string $token): self
    {
        return new self(
            mailSent: $this->mailSent,
            token: $token,
            tokenTtl: $this->tokenTtl,
            lastAttempt: $this->lastAttempt,
        );
    }

    public function withTokenTtl(?int $tokenTtl): self
    {
        return new self(
            mailSent: $this->mailSent,
            token: $this->token,
            tokenTtl: $tokenTtl,
            lastAttempt: $this->lastAttempt,
        );
    }

    public function withLastAttempt(?DateTimeInterface $lastAttempt): self
    {
        return new self(
            mailSent: $this->mailSent,
            token: $this->token,
            tokenTtl: $this->tokenTtl,
            lastAttempt: $lastAttempt,
        );
    }
}
