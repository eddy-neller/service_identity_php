<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Messenger\Event\Handler\User;

use App\Application\User\Port\RefreshTokenRepositoryInterface;
use App\Domain\User\Event\Security\ReauthenticationReason;
use App\Domain\User\Event\Security\UserReauthenticationRequiredEvent;
use App\Infrastructure\Adapter\Token\AuthVersionStoreInterface;
use App\Infrastructure\Symfony\Messenger\Event\DomainEventLedgerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Révoque les sessions actives d'un utilisateur : suppression de ses refresh tokens et
 * rotation de sa version d'authentification (qui invalide les JWT déjà émis).
 *
 * La rotation vit dans Redis, hors transaction : garde d'entrée sur le ledger puis
 * marquage après succès. Un rejeu après crash ne ferait que déconnecter à nouveau.
 */
#[AsMessageHandler(bus: 'event.bus', sign: true)]
final readonly class RevokeSessionsHandler
{
    public function __construct(
        private RefreshTokenRepositoryInterface $refreshTokenRepository,
        private AuthVersionStoreInterface $authVersionStore,
        private DomainEventLedgerInterface $ledger,
    ) {
    }

    public function __invoke(UserReauthenticationRequiredEvent $event): void
    {
        // Un simple changement de rôles ne justifie pas de couper les sessions :
        // le prochain jeton portera les nouveaux rôles.
        if (ReauthenticationReason::ROLES_CHANGED === $event->getReason()) {
            return;
        }

        if ($this->ledger->hasProcessed($event->eventId(), self::class)) {
            return;
        }

        $this->refreshTokenRepository->deleteAllForUser($event->getUserId());
        $this->authVersionStore->rotate($event->getUserId()->toString());

        $this->ledger->markProcessed($event->eventId(), self::class);
    }
}
