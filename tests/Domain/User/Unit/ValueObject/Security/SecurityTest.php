<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\Unit\ValueObject\Security;

use App\Domain\User\ValueObject\Security\Security;
use PHPUnit\Framework\TestCase;

final class SecurityTest extends TestCase
{
    public function testConstructWithDefaultValues(): void
    {
        $security = Security::create();

        $this->assertSame(0, $security->getTotalWrongPassword());
        $this->assertSame(0, $security->getTotalWrongTwoFactorCode());
        $this->assertSame(0, $security->getTotalTwoFactorSmsSent());
    }

    public function testConstructWithSpecificValues(): void
    {
        $security = Security::create(
            totalWrongPassword: 3,
            totalWrongTwoFactorCode: 2,
            totalTwoFactorSmsSent: 5,
        );

        $this->assertSame(3, $security->getTotalWrongPassword());
        $this->assertSame(2, $security->getTotalWrongTwoFactorCode());
        $this->assertSame(5, $security->getTotalTwoFactorSmsSent());
    }

    public function testFromArrayCreatesSecurity(): void
    {
        $security = Security::fromArray([
            'totalWrongPassword' => 4,
            'totalWrongTwoFactorCode' => 1,
            'totalTwoFactorSmsSent' => 6,
        ]);

        $this->assertSame(4, $security->getTotalWrongPassword());
        $this->assertSame(1, $security->getTotalWrongTwoFactorCode());
        $this->assertSame(6, $security->getTotalTwoFactorSmsSent());
    }

    public function testFromArrayUsesDefaultsForMissingValues(): void
    {
        $security = Security::fromArray([]);

        $this->assertSame(0, $security->getTotalWrongPassword());
        $this->assertSame(0, $security->getTotalWrongTwoFactorCode());
        $this->assertSame(0, $security->getTotalTwoFactorSmsSent());
    }

    public function testFromArrayCastsValuesToInt(): void
    {
        $security = Security::fromArray([
            'totalWrongPassword' => '5',
            'totalWrongTwoFactorCode' => '3',
            'totalTwoFactorSmsSent' => '2',
        ]);

        $this->assertSame(5, $security->getTotalWrongPassword());
        $this->assertSame(3, $security->getTotalWrongTwoFactorCode());
        $this->assertSame(2, $security->getTotalTwoFactorSmsSent());
    }

    public function testJsonSerializeReturnsArray(): void
    {
        $security = Security::create(
            totalWrongPassword: 3,
            totalWrongTwoFactorCode: 2,
            totalTwoFactorSmsSent: 5,
        );
        $data = $security->jsonSerialize();

        $this->assertSame([
            'totalWrongPassword' => 3,
            'totalWrongTwoFactorCode' => 2,
            'totalTwoFactorSmsSent' => 5,
        ], $data);
    }

    public function testToArrayReturnsArray(): void
    {
        $security = Security::create(
            totalWrongPassword: 3,
            totalWrongTwoFactorCode: 2,
            totalTwoFactorSmsSent: 5,
        );
        $data = $security->toArray();

        $this->assertSame([
            'totalWrongPassword' => 3,
            'totalWrongTwoFactorCode' => 2,
            'totalTwoFactorSmsSent' => 5,
        ], $data);
    }

    public function testWithTotalWrongPasswordCreatesNewInstance(): void
    {
        $security = Security::create(totalWrongPassword: 2);
        $newSecurity = $security->withTotalWrongPassword(5);

        $this->assertSame(2, $security->getTotalWrongPassword());
        $this->assertSame(5, $newSecurity->getTotalWrongPassword());
    }

    public function testWithTotalWrongPasswordIsImmutable(): void
    {
        $security = Security::create(totalWrongPassword: 2);
        $newSecurity = $security->withTotalWrongPassword(5);

        $this->assertNotSame($security, $newSecurity);
    }

    public function testWithTotalWrongTwoFactorCodeCreatesNewInstance(): void
    {
        $security = Security::create(totalWrongTwoFactorCode: 1);
        $newSecurity = $security->withTotalWrongTwoFactorCode(3);

        $this->assertSame(1, $security->getTotalWrongTwoFactorCode());
        $this->assertSame(3, $newSecurity->getTotalWrongTwoFactorCode());
    }

    public function testWithTotalWrongTwoFactorCodeIsImmutable(): void
    {
        $security = Security::create(totalWrongTwoFactorCode: 1);
        $newSecurity = $security->withTotalWrongTwoFactorCode(3);

        $this->assertNotSame($security, $newSecurity);
    }

    public function testWithTotalTwoFactorSmsSentCreatesNewInstance(): void
    {
        $security = Security::create(totalTwoFactorSmsSent: 4);
        $newSecurity = $security->withTotalTwoFactorSmsSent(8);

        $this->assertSame(4, $security->getTotalTwoFactorSmsSent());
        $this->assertSame(8, $newSecurity->getTotalTwoFactorSmsSent());
    }

    public function testWithTotalTwoFactorSmsSentIsImmutable(): void
    {
        $security = Security::create(totalTwoFactorSmsSent: 4);
        $newSecurity = $security->withTotalTwoFactorSmsSent(8);

        $this->assertNotSame($security, $newSecurity);
    }

    public function testWithMethodsPreserveOtherValues(): void
    {
        $security = Security::create(
            totalWrongPassword: 1,
            totalWrongTwoFactorCode: 2,
            totalTwoFactorSmsSent: 3,
        );

        $newSecurity = $security->withTotalWrongPassword(10);

        $this->assertSame(10, $newSecurity->getTotalWrongPassword());
        $this->assertSame(2, $newSecurity->getTotalWrongTwoFactorCode());
        $this->assertSame(3, $newSecurity->getTotalTwoFactorSmsSent());
    }
}
