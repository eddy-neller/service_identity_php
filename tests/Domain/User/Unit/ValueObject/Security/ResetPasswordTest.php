<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\Unit\ValueObject\Security;

use App\Domain\User\ValueObject\Security\ResetPassword;
use PHPUnit\Framework\TestCase;

final class ResetPasswordTest extends TestCase
{
    public function testConstructWithDefaultValues(): void
    {
        $resetPassword = ResetPassword::create();

        $this->assertSame(0, $resetPassword->getMailSent());
        $this->assertNull($resetPassword->getToken());
        $this->assertNull($resetPassword->getTokenTtl());
    }

    public function testConstructWithSpecificValues(): void
    {
        $resetPassword = ResetPassword::create(
            mailSent: 2,
            token: 'reset-token',
            tokenTtl: 1234567890,
        );

        $this->assertSame(2, $resetPassword->getMailSent());
        $this->assertSame('reset-token', $resetPassword->getToken());
        $this->assertSame(1234567890, $resetPassword->getTokenTtl());
    }

    public function testFromArrayCreatesResetPassword(): void
    {
        $resetPassword = ResetPassword::fromArray([
            'mailSent' => 3,
            'token' => 'test-token',
            'tokenTtl' => 9876543210,
        ]);

        $this->assertSame(3, $resetPassword->getMailSent());
        $this->assertSame('test-token', $resetPassword->getToken());
        $this->assertSame(9876543210, $resetPassword->getTokenTtl());
    }

    public function testFromArrayUsesDefaultsForMissingValues(): void
    {
        $resetPassword = ResetPassword::fromArray([]);

        $this->assertSame(0, $resetPassword->getMailSent());
        $this->assertNull($resetPassword->getToken());
        $this->assertNull($resetPassword->getTokenTtl());
    }

    public function testFromArrayCastsMailSentToInt(): void
    {
        $resetPassword = ResetPassword::fromArray(['mailSent' => '5']);

        $this->assertSame(5, $resetPassword->getMailSent());
    }

    public function testJsonSerializeReturnsArray(): void
    {
        $resetPassword = ResetPassword::create(
            mailSent: 2,
            token: 'reset-token',
            tokenTtl: 1234567890,
        );
        $data = $resetPassword->jsonSerialize();

        $this->assertSame([
            'mailSent' => 2,
            'token' => 'reset-token',
            'tokenTtl' => 1234567890,
        ], $data);
    }

    public function testToArrayReturnsArray(): void
    {
        $resetPassword = ResetPassword::create(
            mailSent: 2,
            token: 'reset-token',
            tokenTtl: 1234567890,
        );
        $data = $resetPassword->toArray();

        $this->assertSame([
            'mailSent' => 2,
            'token' => 'reset-token',
            'tokenTtl' => 1234567890,
        ], $data);
    }

    public function testWithMailSentCreatesNewInstance(): void
    {
        $resetPassword = ResetPassword::create(mailSent: 1);
        $newResetPassword = $resetPassword->withMailSent(3);

        $this->assertSame(1, $resetPassword->getMailSent());
        $this->assertSame(3, $newResetPassword->getMailSent());
    }

    public function testWithMailSentIsImmutable(): void
    {
        $resetPassword = ResetPassword::create(mailSent: 1);
        $newResetPassword = $resetPassword->withMailSent(3);

        $this->assertNotSame($resetPassword, $newResetPassword);
    }

    public function testWithTokenCreatesNewInstance(): void
    {
        $resetPassword = ResetPassword::create(token: 'old-token');
        $newResetPassword = $resetPassword->withToken('new-token');

        $this->assertSame('old-token', $resetPassword->getToken());
        $this->assertSame('new-token', $newResetPassword->getToken());
    }

    public function testWithTokenIsImmutable(): void
    {
        $resetPassword = ResetPassword::create(token: 'old-token');
        $newResetPassword = $resetPassword->withToken('new-token');

        $this->assertNotSame($resetPassword, $newResetPassword);
    }

    public function testWithTokenCanSetNull(): void
    {
        $resetPassword = ResetPassword::create(token: 'token');
        $newResetPassword = $resetPassword->withToken(null);

        $this->assertNull($newResetPassword->getToken());
    }

    public function testWithTokenTtlCreatesNewInstance(): void
    {
        $resetPassword = ResetPassword::create(tokenTtl: 1000);
        $newResetPassword = $resetPassword->withTokenTtl(2000);

        $this->assertSame(1000, $resetPassword->getTokenTtl());
        $this->assertSame(2000, $newResetPassword->getTokenTtl());
    }

    public function testWithTokenTtlIsImmutable(): void
    {
        $resetPassword = ResetPassword::create(tokenTtl: 1000);
        $newResetPassword = $resetPassword->withTokenTtl(2000);

        $this->assertNotSame($resetPassword, $newResetPassword);
    }

    public function testWithTokenTtlCanSetNull(): void
    {
        $resetPassword = ResetPassword::create(tokenTtl: 1000);
        $newResetPassword = $resetPassword->withTokenTtl(null);

        $this->assertNull($newResetPassword->getTokenTtl());
    }

    public function testWithMethodsPreserveOtherValues(): void
    {
        $resetPassword = ResetPassword::create(
            mailSent: 1,
            token: 'token',
            tokenTtl: 1000,
        );

        $newResetPassword = $resetPassword->withMailSent(5);

        $this->assertSame(5, $newResetPassword->getMailSent());
        $this->assertSame('token', $newResetPassword->getToken());
        $this->assertSame(1000, $newResetPassword->getTokenTtl());
    }
}
