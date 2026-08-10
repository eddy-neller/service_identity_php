<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Unit\Service\Token;

use App\Infrastructure\Service\Token\RedisAuthVersionStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class RedisAuthVersionStoreTest extends TestCase
{
    private const string USER_ID = '550e8400-e29b-41d4-a716-446655440000';

    public function testGetOrCreatePersistsAnAuthVersion(): void
    {
        $store = new RedisAuthVersionStore(new ArrayAdapter());

        $authVersion = $store->getOrCreate(self::USER_ID);

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $authVersion);
        $this->assertSame($authVersion, $store->getOrCreate(self::USER_ID));
        $this->assertTrue($store->matches(self::USER_ID, $authVersion));
    }

    public function testRotateInvalidatesThePreviousAuthVersion(): void
    {
        $store = new RedisAuthVersionStore(new ArrayAdapter());
        $previousVersion = $store->getOrCreate(self::USER_ID);

        $currentVersion = $store->rotate(self::USER_ID);

        $this->assertNotSame($previousVersion, $currentVersion);
        $this->assertFalse($store->matches(self::USER_ID, $previousVersion));
        $this->assertTrue($store->matches(self::USER_ID, $currentVersion));
    }

    public function testMatchesRejectsAnUnknownUser(): void
    {
        $store = new RedisAuthVersionStore(new ArrayAdapter());

        $this->assertFalse($store->matches(self::USER_ID, 'unknown-version'));
    }
}
