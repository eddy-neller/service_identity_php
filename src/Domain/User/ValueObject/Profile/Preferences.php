<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObject\Profile;

use App\Domain\User\Exception\Profile\InvalidPreferencesException;
use JsonSerializable;

final readonly class Preferences implements JsonSerializable
{
    public const array SUPPORTED_LANGS = ['en', 'fr'];

    public const string DEFAULT_LANG = 'en';

    private string $lang;

    private function __construct(string $lang = self::DEFAULT_LANG)
    {
        $this->lang = $this->normalizeLang($lang);
    }

    public static function create(string $lang = self::DEFAULT_LANG): self
    {
        return new self(lang: $lang);
    }

    public static function fromArray(array $data): self
    {
        $lang = $data['lang'] ?? self::DEFAULT_LANG;

        return new self(
            lang: is_string($lang) ? $lang : self::DEFAULT_LANG,
        );
    }

    private function normalizeLang(string $lang): string
    {
        $normalized = strtolower($lang);

        if (!in_array($normalized, self::SUPPORTED_LANGS, true)) {
            throw InvalidPreferencesException::unsupportedLang($lang);
        }

        return $normalized;
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
