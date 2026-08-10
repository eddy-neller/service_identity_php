<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger\Event\Handler\User;

use App\Application\Shared\Port\ClockInterface;
use App\Application\User\Port\TokenProviderInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Domain\User\Event\Lifecycle\ActivationEmailRequestedEvent;
use App\Infrastructure\Messenger\Event\DomainEventLedgerInterface;
use App\Infrastructure\Notification\User\UserNotifierInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Envoie l'e-mail d'activation correspondant à une demande.
 *
 * Le token n'est pas transporté par l'événement — le persister dans l'outbox reviendrait à
 * stocker un secret en clair, avec la rétention du transport `failed`. Il est donc relu sur
 * l'agrégat. En contrepartie, un événement rejoué tardivement peut tomber sur un token
 * remplacé ou expiré : c'est un no-op métier normal, journalisé et marqué comme traité.
 */
#[AsMessageHandler(bus: 'event.bus', sign: true)]
final readonly class SendActivationEmailHandler
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private TokenProviderInterface $tokenProvider,
        private UserNotifierInterface $notifier,
        private DomainEventLedgerInterface $ledger,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ActivationEmailRequestedEvent $event): void
    {
        if ($this->ledger->hasProcessed($event->eventId(), self::class)) {
            return;
        }

        $user = $this->repository->findById($event->getUserId());

        if (null === $user) {
            $this->logger->info('Activation email skipped because the user no longer exists.', [
                'user_id' => $event->getUserId()->toString(),
                'event_id' => $event->eventId(),
                'reason' => 'user_not_found',
            ]);
            $this->ledger->markProcessed($event->eventId(), self::class);

            return;
        }

        $activeEmail = $user->getActiveEmail();
        $token = $activeEmail->getToken();
        $tokenTtl = $activeEmail->getTokenTtl();

        if (null === $token || null === $tokenTtl || $tokenTtl < $this->clock->now()->getTimestamp()) {
            $this->logger->info('Activation email skipped because its token is stale.', [
                'user_id' => $event->getUserId()->toString(),
                'event_id' => $event->eventId(),
                'reason' => null === $token ? 'token_missing' : (null === $tokenTtl ? 'token_ttl_missing' : 'token_expired'),
            ]);
            $this->ledger->markProcessed($event->eventId(), self::class);

            return;
        }

        $this->notifier->sendActivationEmail($user, $this->tokenProvider->encode($token, $event->getEmail()));

        $this->ledger->markProcessed($event->eventId(), self::class);
    }
}
