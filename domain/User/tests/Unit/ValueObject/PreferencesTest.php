<?php

declare(strict_types=1);

namespace App\Domain\User\Tests\Unit\ValueObject;

use App\Domain\User\ValueObject\Preferences;
use PHPUnit\Framework\TestCase;

final class PreferencesTest extends TestCase
{
    public function testConstructWithDefaultValues(): void
    {
        $preferences = new Preferences();

        $this->assertSame('fr', $preferences->getLang());
    }

    public function testConstructWithSpecificLang(): void
    {
        $preferences = new Preferences(lang: 'en');

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

        $this->assertSame('fr', $preferences->getLang());
    }

    public function testJsonSerializeReturnsArray(): void
    {
        $preferences = new Preferences(lang: 'en');
        $data = $preferences->jsonSerialize();

        $this->assertSame(['lang' => 'en'], $data);
    }

    public function testToArrayReturnsArray(): void
    {
        $preferences = new Preferences(lang: 'en');
        $data = $preferences->toArray();

        $this->assertSame(['lang' => 'en'], $data);
    }

    public function testGetLangReturnsLang(): void
    {
        $preferences = new Preferences(lang: 'es');

        $this->assertSame('es', $preferences->getLang());
    }

    public function testWithLangCreatesNewInstanceWithNewLang(): void
    {
        $preferences = new Preferences(lang: 'en');
        $newPreferences = $preferences->withLang('de');

        $this->assertSame('en', $preferences->getLang());
        $this->assertSame('de', $newPreferences->getLang());
    }

    public function testWithLangIsImmutable(): void
    {
        $preferences = new Preferences(lang: 'en');
        $newPreferences = $preferences->withLang('de');

        $this->assertNotSame($preferences, $newPreferences);
    }
}
