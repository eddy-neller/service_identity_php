<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger\Event\Handler\User;

use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\UseCase\Command\Customer\CreateCustomer\CreateCustomerCommand;
use App\Domain\Shop\Customer\Exception\CustomerAlreadyExistsException;
use App\Domain\User\Event\Lifecycle\UserRegisteredEvent;
use App\Domain\User\Event\Management\UserCreatedByAdminEvent;
use App\Infrastructure\Messenger\Event\DomainEventLedgerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Provisionne le Customer Shop rattaché à un compte utilisateur nouvellement créé.
 *
 * L'effet est en base : le marquage dans le ledger partage la transaction de la création,
 * donc une redélivrance ne peut pas produire un second Customer.
 */
final readonly class ProvisionCustomerHandler
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private DomainEventLedgerInterface $ledger,
        private TransactionalInterface $transactional,
        private LoggerInterface $logger,
    ) {
    }

    #[AsMessageHandler(bus: 'event.bus', sign: true)]
    public function onUserRegistered(UserRegisteredEvent $event): void
    {
        $this->provision($event->eventId(), $event->getUserId()->toString());
    }

    #[AsMessageHandler(bus: 'event.bus', sign: true)]
    public function onUserCreatedByAdmin(UserCreatedByAdminEvent $event): void
    {
        $this->provision($event->eventId(), $event->getUserId()->toString());
    }

    private function provision(string $eventId, string $userId): void
    {
        $this->transactional->transactional(function () use ($eventId, $userId): void {
            if ($this->ledger->hasProcessed($eventId, self::class)) {
                return;
            }

            try {
                $this->commandBus->dispatch(new CreateCustomerCommand($userId));
            } catch (CustomerAlreadyExistsException) {
                // Le Customer existe déjà : redélivrance, ou création concurrente arbitrée
                // par la contrainte d'unicité sur `shop_customer.user_account_id`.
                // La réaction est donc satisfaite — on marque et on sort.
                $this->logger->info('Customer already provisioned for user', [
                    'user_id' => $userId,
                    'event_id' => $eventId,
                ]);
            }

            $this->ledger->markProcessed($eventId, self::class);
        });
    }
}
