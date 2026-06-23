<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObject;

use JsonSerializable;

final readonly class Preferences implements JsonSerializable
{
    public function __construct(
        private string $lang = 'fr',
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            lang: $data['lang'] ?? 'fr',
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'lang' => $this->lang,
        ];
    }

    public function toArray(): array
    {
        return $this->jsonSerialize();
    }

    public function getLang(): string
    {
        return $this->lang;
    }

    public function withLang(string $lang): self
    {
        return new self(lang: $lang);
    }
}
