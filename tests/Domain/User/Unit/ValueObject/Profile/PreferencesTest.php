<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\Unit\ValueObject\Profile;

use App\Domain\User\Exception\Profile\InvalidPreferencesException;
use App\Domain\User\ValueObject\Profile\Preferences;
use PHPUnit\Framework\TestCase;

final class PreferencesTest extends TestCase
{
    public function testConstructWithDefaultValues(): void
    {
        $preferences = Preferences::create();

        $this->assertSame('en', $preferences->getLang());
    }

    public function testConstructWithSpecificLang(): void
    {
        $preferences = Preferences::create(lang: 'en');

        $this->assertSame('en', $preferences->getLang());
    }

    public function testFromArrayCreatesPreferences(): void
    {
        $preferences = Preferences::fromArray(['lang' => 'en']);

        $this->assertSame('en', $preferences->getLang());
    }

    public function testFromArrayUsesDefaultsForMissingValues(): void
    {
        $preferences = Preferences::fromArray([]);

        $this->assertSame('en', $preferences->getLang());
    }

    public function testJsonSerializeReturnsArray(): void
    {
        $preferences = Preferences::create(lang: 'en');
        $data = $preferences->jsonSerialize();

        $this->assertSame(['lang' => 'en'], $data);
    }

    public function testToArrayReturnsArray(): void
    {
        $preferences = Preferences::create(lang: 'en');
        $data = $preferences->toArray();

        $this->assertSame(['lang' => 'en'], $data);
    }

    public function testGetLangReturnsLang(): void
    {
        $preferences = Preferences::create(lang: 'fr');

        $this->assertSame('fr', $preferences->getLang());
    }

    public function testConstructNormalizesLangToLowercase(): void
    {
        $preferences = Preferences::create(lang: 'EN');

        $this->assertSame('en', $preferences->getLang());
    }

    public function testConstructRejectsUnsupportedLang(): void
    {
        $this->expectException(InvalidPreferencesException::class);

        Preferences::create(lang: 'es');
    }

    public function testFromArrayRejectsUnsupportedLang(): void
    {
        $this->expectException(InvalidPreferencesException::class);

        Preferences::fromArray(['lang' => 'de']);
    }

    public function testWithLangCreatesNewInstanceWithNewLang(): void
    {
        $preferences = Preferences::create(lang: 'en');
        $newPreferences = $preferences->withLang('fr');

        $this->assertSame('en', $preferences->getLang());
        $this->assertSame('fr', $newPreferences->getLang());
    }

    public function testWithLangRejectsUnsupportedLang(): void
    {
        $preferences = Preferences::create(lang: 'en');

        $this->expectException(InvalidPreferencesException::class);

        $preferences->withLang('de');
    }

    public function testWithLangIsImmutable(): void
    {
        $preferences = Preferences::create(lang: 'en');
        $newPreferences = $preferences->withLang('fr');

        $this->assertNotSame($preferences, $newPreferences);
    }
}
