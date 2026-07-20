<?php

declare(strict_types=1);

namespace App\Domain\User\Tests\Unit\ValueObject\Security;

use App\Domain\User\ValueObject\Security\ActiveEmail;
use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class ActiveEmailTest extends TestCase
{
    public function testConstructWithDefaultValues(): void
    {
        $activeEmail = new ActiveEmail();

        $this->assertSame(0, $activeEmail->getMailSent());
        $this->assertNull($activeEmail->getToken());
        $this->assertNull($activeEmail->getTokenTtl());
        $this->assertNull($activeEmail->getLastAttempt());
    }

    public function testConstructWithSpecificValues(): void
    {
        $lastAttempt = new DateTimeImmutable('2024-01-01T12:00:00+00:00');
        $activeEmail = new ActiveEmail(
            mailSent: 2,
            token: 'activation-token',
            tokenTtl: 1234567890,
            lastAttempt: $lastAttempt,
        );

        $this->assertSame(2, $activeEmail->getMailSent());
        $this->assertSame('activation-token', $activeEmail->getToken());
        $this->assertSame(1234567890, $activeEmail->getTokenTtl());
        $this->assertSame($lastAttempt, $activeEmail->getLastAttempt());
    }

    public function testFromArrayCreatesActiveEmail(): void
    {
        $activeEmail = ActiveEmail::fromArray([
            'mailSent' => 3,
            'token' => 'test-token',
            'tokenTtl' => 9876543210,
            'lastAttempt' => '2024-01-01 12:00:00',
        ]);

        $this->assertSame(3, $activeEmail->getMailSent());
        $this->assertSame('test-token', $activeEmail->getToken());
        $this->assertSame(9876543210, $activeEmail->getTokenTtl());
        $this->assertInstanceOf(DateTimeInterface::class, $activeEmail->getLastAttempt());
    }

    public function testFromArrayUsesDefaultsForMissingValues(): void
    {
        $activeEmail = ActiveEmail::fromArray([]);

        $this->assertSame(0, $activeEmail->getMailSent());
        $this->assertNull($activeEmail->getToken());
        $this->assertNull($activeEmail->getTokenTtl());
        $this->assertNull($activeEmail->getLastAttempt());
    }

    public function testFromArrayCastsMailSentToInt(): void
    {
        $activeEmail = ActiveEmail::fromArray(['mailSent' => '5']);

        $this->assertSame(5, $activeEmail->getMailSent());
    }

    public function testFromArrayAcceptsDateTimeInterface(): void
    {
        $date = new DateTimeImmutable('2024-01-01T12:00:00+00:00');
        $activeEmail = ActiveEmail::fromArray(['lastAttempt' => $date]);

        $this->assertSame($date, $activeEmail->getLastAttempt());
    }

    public function testFromArrayThrowsExceptionForInvalidDate(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Unable to parse the "lastAttempt" date value.');

        ActiveEmail::fromArray(['lastAttempt' => 'invalid-date']);
    }

    public function testJsonSerializeReturnsArray(): void
    {
        $lastAttempt = new DateTimeImmutable('2024-01-01T12:00:00+00:00');
        $activeEmail = new ActiveEmail(
            mailSent: 2,
            token: 'activation-token',
            tokenTtl: 1234567890,
            lastAttempt: $lastAttempt,
        );
        $data = $activeEmail->jsonSerialize();

        $this->assertSame(2, $data['mailSent']);
        $this->assertSame('activation-token', $data['token']);
        $this->assertSame(1234567890, $data['tokenTtl']);
        $this->assertSame('2024-01-01T12:00:00+00:00', $data['lastAttempt']);
    }

    public function testJsonSerializeWithNullLastAttempt(): void
    {
        $activeEmail = new ActiveEmail(mailSent: 1);
        $data = $activeEmail->jsonSerialize();

        $this->assertNull($data['lastAttempt']);
    }

    public function testToArrayReturnsArray(): void
    {
        $lastAttempt = new DateTimeImmutable('2024-01-01T12:00:00+00:00');
        $activeEmail = new ActiveEmail(
            mailSent: 2,
            token: 'activation-token',
            tokenTtl: 1234567890,
            lastAttempt: $lastAttempt,
        );
        $data = $activeEmail->toArray();

        $this->assertSame(2, $data['mailSent']);
        $this->assertSame('activation-token', $data['token']);
        $this->assertSame(1234567890, $data['tokenTtl']);
        $this->assertSame('2024-01-01T12:00:00+00:00', $data['lastAttempt']);
    }

    public function testWithMailSentCreatesNewInstance(): void
    {
        $activeEmail = new ActiveEmail(mailSent: 1);
        $newActiveEmail = $activeEmail->withMailSent(3);

        $this->assertSame(1, $activeEmail->getMailSent());
        $this->assertSame(3, $newActiveEmail->getMailSent());
    }

    public function testWithMailSentIsImmutable(): void
    {
        $activeEmail = new ActiveEmail(mailSent: 1);
        $newActiveEmail = $activeEmail->withMailSent(3);

        $this->assertNotSame($activeEmail, $newActiveEmail);
    }

    public function testWithTokenCreatesNewInstance(): void
    {
        $activeEmail = new ActiveEmail(token: 'old-token');
        $newActiveEmail = $activeEmail->withToken('new-token');

        $this->assertSame('old-token', $activeEmail->getToken());
        $this->assertSame('new-token', $newActiveEmail->getToken());
    }

    public function testWithTokenIsImmutable(): void
    {
        $activeEmail = new ActiveEmail(token: 'old-token');
        $newActiveEmail = $activeEmail->withToken('new-token');

        $this->assertNotSame($activeEmail, $newActiveEmail);
    }

    public function testWithTokenCanSetNull(): void
    {
        $activeEmail = new ActiveEmail(token: 'token');
        $newActiveEmail = $activeEmail->withToken(null);

        $this->assertNull($newActiveEmail->getToken());
    }

    public function testWithTokenTtlCreatesNewInstance(): void
    {
        $activeEmail = new ActiveEmail(tokenTtl: 1000);
        $newActiveEmail = $activeEmail->withTokenTtl(2000);

        $this->assertSame(1000, $activeEmail->getTokenTtl());
        $this->assertSame(2000, $newActiveEmail->getTokenTtl());
    }

    public function testWithTokenTtlIsImmutable(): void
    {
        $activeEmail = new ActiveEmail(tokenTtl: 1000);
        $newActiveEmail = $activeEmail->withTokenTtl(2000);

        $this->assertNotSame($activeEmail, $newActiveEmail);
    }

    public function testWithTokenTtlCanSetNull(): void
    {
        $activeEmail = new ActiveEmail(tokenTtl: 1000);
        $newActiveEmail = $activeEmail->withTokenTtl(null);

        $this->assertNull($newActiveEmail->getTokenTtl());
    }

    public function testWithLastAttemptCreatesNewInstance(): void
    {
        $oldDate = new DateTimeImmutable('2024-01-01T12:00:00+00:00');
        $newDate = new DateTimeImmutable('2024-01-02T12:00:00+00:00');
        $activeEmail = new ActiveEmail(lastAttempt: $oldDate);
        $newActiveEmail = $activeEmail->withLastAttempt($newDate);

        $this->assertSame($oldDate, $activeEmail->getLastAttempt());
        $this->assertSame($newDate, $newActiveEmail->getLastAttempt());
    }

    public function testWithLastAttemptIsImmutable(): void
    {
        $date = new DateTimeImmutable('2024-01-01T12:00:00+00:00');
        $activeEmail = new ActiveEmail(lastAttempt: $date);
        $newActiveEmail = $activeEmail->withLastAttempt(new DateTimeImmutable('2024-01-02'));

        $this->assertNotSame($activeEmail, $newActiveEmail);
    }

    public function testWithLastAttemptCanSetNull(): void
    {
        $activeEmail = new ActiveEmail(lastAttempt: new DateTimeImmutable());
        $newActiveEmail = $activeEmail->withLastAttempt(null);

        $this->assertNull($newActiveEmail->getLastAttempt());
    }

    public function testWithMethodsPreserveOtherValues(): void
    {
        $lastAttempt = new DateTimeImmutable('2024-01-01T12:00:00+00:00');
        $activeEmail = new ActiveEmail(
            mailSent: 1,
            token: 'token',
            tokenTtl: 1000,
            lastAttempt: $lastAttempt,
        );

        $newActiveEmail = $activeEmail->withMailSent(5);

        $this->assertSame(5, $newActiveEmail->getMailSent());
        $this->assertSame('token', $newActiveEmail->getToken());
        $this->assertSame(1000, $newActiveEmail->getTokenTtl());
        $this->assertSame($lastAttempt, $newActiveEmail->getLastAttempt());
    }
}
