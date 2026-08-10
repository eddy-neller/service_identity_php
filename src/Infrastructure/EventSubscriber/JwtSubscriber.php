<?php

declare(strict_types=1);

namespace App\Infrastructure\EventSubscriber;

use App\Infrastructure\Service\InfoCodes;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTExpiredEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTInvalidEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTNotFoundEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationFailureResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class JwtSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            Events::JWT_INVALID => ['onJWTInvalid'],
            Events::JWT_NOT_FOUND => ['onJWTNotFound'],
            Events::JWT_EXPIRED => ['onJWTExpired'],
        ];
    }

    public function onJWTInvalid(JWTInvalidEvent $event): void
    {
        $response = new JWTAuthenticationFailureResponse(InfoCodes::JWT['INVALID_TOKEN']);

        $event->setResponse($response);
    }

    public function onJWTNotFound(JWTNotFoundEvent $event): void
    {
        $response = new JWTAuthenticationFailureResponse(InfoCodes::JWT['MISSING_TOKEN']);

        $event->setResponse($response);
    }

    public function onJWTExpired(JWTExpiredEvent $event): void
    {
        $response = new JWTAuthenticationFailureResponse(InfoCodes::JWT['EXPIRED_TOKEN']);

        $event->setResponse($response);
    }
}
