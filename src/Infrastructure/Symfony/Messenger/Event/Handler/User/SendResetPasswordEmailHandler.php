<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Messenger\Event\Handler\User;

use App\Application\Shared\Port\ClockInterface;
use App\Application\User\Port\TokenProviderInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Domain\User\Event\Security\PasswordResetRequestedEvent;
use App\Infrastructure\Symfony\Messenger\Event\DomainEventLedgerInterface;
use App\Infrastructure\Symfony\Service\Notification\User\UserNotifierInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Envoie l'e-mail de réinitialisation de mot de passe correspondant à une demande.
 *
 * Même contrat que `SendActivationEmailHandler` : le token est relu sur l'agrégat plutôt que
 * transporté par l'événement, et un token remplacé ou expiré devient un no-op métier journalisé.
 */
#[AsMessageHandler(bus: 'event.bus', sign: true)]
final readonly class SendResetPasswordEmailHandler
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

    public function __invoke(PasswordResetRequestedEvent $event): void
    {
        if ($this->ledger->hasProcessed($event->eventId(), self::class)) {
            return;
        }

        $user = $this->repository->findById($event->getUserId());

        if (null === $user) {
            $this->logger->info('Password reset email skipped because the user no longer exists.', [
                'user_id' => $event->getUserId()->toString(),
                'event_id' => $event->eventId(),
                'reason' => 'user_not_found',
            ]);
            $this->ledger->markProcessed($event->eventId(), self::class);

            return;
        }

        $resetPassword = $user->getResetPassword();
        $token = $resetPassword->getToken();
        $tokenTtl = $resetPassword->getTokenTtl();

        if (null === $token || null === $tokenTtl || $tokenTtl < $this->clock->now()->getTimestamp()) {
            $this->logger->info('Password reset email skipped because its token is stale.', [
                'user_id' => $event->getUserId()->toString(),
                'event_id' => $event->eventId(),
                'reason' => null === $token ? 'token_missing' : (null === $tokenTtl ? 'token_ttl_missing' : 'token_expired'),
            ]);
            $this->ledger->markProcessed($event->eventId(), self::class);

            return;
        }

        $this->notifier->sendResetPasswordEmail($user, $this->tokenProvider->encode($token, $event->getEmail()));

        $this->ledger->markProcessed($event->eventId(), self::class);
    }
}
