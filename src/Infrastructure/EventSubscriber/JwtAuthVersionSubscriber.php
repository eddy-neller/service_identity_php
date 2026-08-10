<?php

declare(strict_types=1);

namespace App\Infrastructure\EventSubscriber;

use App\Infrastructure\Service\Token\AuthVersionStoreInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Ramsey\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class JwtAuthVersionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private AuthVersionStoreInterface $authVersionStore,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::JWT_DECODED => 'onJWTDecoded',
        ];
    }

    public function onJWTDecoded(JWTDecodedEvent $event): void
    {
        $payload = $event->getPayload();
        $subject = $payload['sub'] ?? null;
        $authVersion = $payload['auth_version'] ?? null;

        if (
            !is_string($subject)
            || !Uuid::isValid($subject)
            || !is_string($authVersion)
            || !$this->authVersionStore->matches($subject, $authVersion)
        ) {
            $event->markAsInvalid();
        }
    }
}
