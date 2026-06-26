<?php

declare(strict_types=1);

namespace App\Infrastructure\EventSubscriber\User;

use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\Shop\Port\CustomerRepositoryInterface;
use App\Application\Shop\UseCase\Command\Customer\CreateCustomer\CreateCustomerCommand;
use App\Application\Shop\UseCase\Command\Customer\DisableCustomer\DisableCustomerCommand;
use App\Application\User\Port\TokenProviderInterface;
use App\Application\User\Port\UserNotifierInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Domain\Shop\Customer\ValueObject\UserAccountId;
use App\Domain\User\Event\ActivationEmailRequestedEvent;
use App\Domain\User\Event\PasswordResetCompletedEvent;
use App\Domain\User\Event\PasswordResetRequestedEvent;
use App\Domain\User\Event\UserActivatedEvent;
use App\Domain\User\Event\UserAvatarUpdatedEvent;
use App\Domain\User\Event\UserCreatedByAdminEvent;
use App\Domain\User\Event\UserDeletedByAdminEvent;
use App\Domain\User\Event\UserPasswordUpdatedEvent;
use App\Domain\User\Event\UserRegisteredEvent;
use App\Domain\User\Event\UserUpdatedByAdminEvent;
use App\Domain\User\Event\UserWrongPasswordAttemptRegisteredEvent;
use App\Domain\User\Event\UserWrongPasswordAttemptsResetEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class UserEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private CustomerRepositoryInterface $customerRepository,
        private TokenProviderInterface $tokenProvider,
        private UserNotifierInterface $notifier,
        private LoggerInterface $logger,
        private CommandBusInterface $commandBus,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'user.registered' => 'onUserRegistered',
            'user.password_reset.requested' => 'onPasswordResetRequested',
            'user.activation_email.requested' => 'onActivationEmailRequested',
            'user.activated' => 'onUserActivated',
            'user.password_reset.completed' => 'onPasswordResetCompleted',
            'user.deleted' => 'onUserDeleted',
            'user.created_by_admin' => 'onUserCreatedByAdmin',
            'user.updated_by_admin' => 'onUserUpdatedByAdmin',
            'user.avatar_updated' => 'onUserAvatarUpdated',
            'user.password.updated' => 'onUserPasswordUpdated',
            'user.wrong_password_attempt.registered' => 'onUserWrongPasswordAttemptRegistered',
            'user.wrong_password_attempts.reset' => 'onUserWrongPasswordAttemptsReset',
        ];
    }

    public function onUserRegistered(UserRegisteredEvent $event): void
    {
        $this->logger->info('User registered', [
            'user_id' => $event->getUserId()->toString(),
            'email' => $event->getEmail()->toString(),
            'occurred_on' => $event->occurredOn()->format('Y-m-d H:i:s'),
        ]);

        $this->syncCustomerForUser($event->getUserId()->toString());

        $user = $this->repository->findById($event->getUserId());

        if (null === $user) {
            return;
        }

        $activeEmail = $user->getActiveEmail();
        $token = $activeEmail->getToken();

        if (null !== $token) {
            $encoded = $this->tokenProvider->encode($token, $event->getEmail());
            $this->notifier->sendActivationEmail($user, $encoded);
        }
    }

    public function onPasswordResetRequested(PasswordResetRequestedEvent $event): void
    {
        $this->logger->info('Password reset requested', [
            'user_id' => $event->getUserId()->toString(),
            'email' => $event->getEmail()->toString(),
            'occurred_on' => $event->occurredOn()->format('Y-m-d H:i:s'),
        ]);

        $user = $this->repository->findById($event->getUserId());

        if (null === $user) {
            return;
        }

        $resetPassword = $user->getResetPassword();
        $token = $resetPassword->getToken();

        if (null !== $token) {
            $encoded = $this->tokenProvider->encode($token, $event->getEmail());
            $this->notifier->sendResetPasswordEmail($user, $encoded);
        }
    }

    public function onActivationEmailRequested(ActivationEmailRequestedEvent $event): void
    {
        $this->logger->info('Activation email requested', [
            'user_id' => $event->getUserId()->toString(),
            'email' => $event->getEmail()->toString(),
            'occurred_on' => $event->occurredOn()->format('Y-m-d H:i:s'),
        ]);

        $user = $this->repository->findById($event->getUserId());

        if (null === $user) {
            return;
        }

        $activeEmail = $user->getActiveEmail();
        $token = $activeEmail->getToken();

        if (null !== $token) {
            $encoded = $this->tokenProvider->encode($token, $event->getEmail());
            $this->notifier->sendActivationEmail($user, $encoded);
        }
    }

    public function onUserActivated(UserActivatedEvent $event): void
    {
        $this->logger->info('User activated', [
            'user_id' => $event->getUserId()->toString(),
            'occurred_on' => $event->occurredOn()->format('Y-m-d H:i:s'),
        ]);

        // Ici, on peut ajouter d'autres actions :
        // - Envoyer un email de bienvenue
        // - Créer des données par défaut
        // - Notifier d'autres systèmes
    }

    public function onPasswordResetCompleted(PasswordResetCompletedEvent $event): void
    {
        $this->logger->info('Password reset completed', [
            'user_id' => $event->getUserId()->toString(),
            'occurred_on' => $event->occurredOn()->format('Y-m-d H:i:s'),
        ]);

        // Ici, on peut ajouter d'autres actions :
        // - Envoyer un email de confirmation
        // - Notifier les systèmes de sécurité
        // - Invalider les sessions actives
    }

    public function onUserDeleted(UserDeletedByAdminEvent $event): void
    {
        $this->logger->info('User deleted', [
            'user_id' => $event->getUserId()->toString(),
            'occurred_on' => $event->occurredOn()->format('Y-m-d H:i:s'),
        ]);

        $customer = $this->customerRepository->findByUserAccountId(
            UserAccountId::fromString($event->getUserId()->toString()),
        );

        if (null !== $customer) {
            $this->commandBus->dispatch(new DisableCustomerCommand($customer->getId()));
        }

        // Ici, on peut ajouter d'autres actions :
        // - Nettoyer les données associées
        // - Notifier les systèmes externes
        // - Archiver les données
    }

    public function onUserCreatedByAdmin(UserCreatedByAdminEvent $event): void
    {
        $this->logger->info('User created by admin', [
            'user_id' => $event->getUserId()->toString(),
            'email' => $event->getEmail()->toString(),
            'occurred_on' => $event->occurredOn()->format('Y-m-d H:i:s'),
        ]);

        $this->syncCustomerForUser($event->getUserId()->toString());

        // Ici, on peut ajouter d'autres actions :
        // - Envoyer un email de notification à l'utilisateur
        // - Créer des données par défaut
        // - Notifier d'autres systèmes
    }

    public function onUserUpdatedByAdmin(UserUpdatedByAdminEvent $event): void
    {
        $this->logger->info('User updated by admin', [
            'user_id' => $event->getUserId()->toString(),
            'occurred_on' => $event->occurredOn()->format('Y-m-d H:i:s'),
        ]);

        // Ici, on peut ajouter d'autres actions :
        // - Notifier l'utilisateur des changements
        // - Synchroniser avec d'autres systèmes
        // - Archiver les modifications
    }

    public function onUserAvatarUpdated(UserAvatarUpdatedEvent $event): void
    {
        $this->logger->info('User avatar updated', [
            'user_id' => $event->getUserId()->toString(),
            'occurred_on' => $event->occurredOn()->format('Y-m-d H:i:s'),
        ]);

        // Ici, on peut ajouter d'autres actions :
        // - Notifier l'utilisateur
        // - Nettoyer d'anciens fichiers
        // - Synchroniser avec d'autres systèmes
    }

    public function onUserPasswordUpdated(UserPasswordUpdatedEvent $event): void
    {
        $this->logger->info('User password updated', [
            'user_id' => $event->getUserId()->toString(),
            'occurred_on' => $event->occurredOn()->format('Y-m-d H:i:s'),
        ]);

        // Ici, on peut ajouter d'autres actions :
        // - Envoyer un email de confirmation
        // - Invalider les sessions actives
        // - Notifier les systèmes de sécurité
    }

    public function onUserWrongPasswordAttemptRegistered(UserWrongPasswordAttemptRegisteredEvent $event): void
    {
        $this->logger->info('User wrong password attempt registered', [
            'user_id' => $event->getUserId()->toString(),
            'occurred_on' => $event->occurredOn()->format('Y-m-d H:i:s'),
        ]);

        // Ici, on peut ajouter d'autres actions :
        // - Déclencher des alertes de sécurité
        // - Notifier un SIEM
        // - Journaliser pour analyse
    }

    public function onUserWrongPasswordAttemptsReset(UserWrongPasswordAttemptsResetEvent $event): void
    {
        $this->logger->info('User wrong password attempts reset', [
            'user_id' => $event->getUserId()->toString(),
            'occurred_on' => $event->occurredOn()->format('Y-m-d H:i:s'),
        ]);

        // Ici, on peut ajouter d'autres actions :
        // - Notifier l'utilisateur
        // - Journaliser pour audit
        // - Mettre à jour des métriques
    }

    private function syncCustomerForUser(string $userId): void
    {
        $this->commandBus->dispatch(new CreateCustomerCommand(
            UserAccountId::fromString($userId),
        ));
    }
}
