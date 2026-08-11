<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Unit\Service\Token;

use App\Infrastructure\Adapter\Token\AuthVersionStoreInterface;
use App\Infrastructure\Symfony\EventSubscriber\JwtAuthVersionSubscriber;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class JwtAuthVersionSubscriberTest extends TestCase
{
    private AuthVersionStoreInterface&MockObject $authVersionStore;

    private JwtAuthVersionSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->authVersionStore = $this->createMock(AuthVersionStoreInterface::class);
        $this->subscriber = new JwtAuthVersionSubscriber($this->authVersionStore);
    }

    public function testOnJWTDecodedKeepsAValidCurrentToken(): void
    {
        $event = new JWTDecodedEvent([
            'sub' => '550e8400-e29b-41d4-a716-446655440000',
            'auth_version' => 'current-version',
        ]);

        $this->authVersionStore->expects($this->once())
            ->method('matches')
            ->with('550e8400-e29b-41d4-a716-446655440000', 'current-version')
            ->willReturn(true);

        $this->subscriber->onJWTDecoded($event);

        $this->assertTrue($event->isValid());
    }

    public function testOnJWTDecodedRejectsAnOutdatedToken(): void
    {
        $event = new JWTDecodedEvent([
            'sub' => '550e8400-e29b-41d4-a716-446655440000',
            'auth_version' => 'outdated-version',
        ]);

        $this->authVersionStore->expects($this->once())
            ->method('matches')
            ->willReturn(false);

        $this->subscriber->onJWTDecoded($event);

        $this->assertFalse($event->isValid());
    }

    public function testOnJWTDecodedRejectsMissingAuthVersionWithoutLoadingTheStore(): void
    {
        $event = new JWTDecodedEvent(['sub' => '550e8400-e29b-41d4-a716-446655440000']);

        $this->authVersionStore->expects($this->never())
            ->method('matches');

        $this->subscriber->onJWTDecoded($event);

        $this->assertFalse($event->isValid());
    }
}
