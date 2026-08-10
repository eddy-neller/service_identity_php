<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger\Event\Handler\User;

use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\CustomerRepositoryInterface;
use App\Application\Shop\UseCase\Command\Customer\DisableCustomer\DisableCustomerCommand;
use App\Domain\Shop\Customer\ValueObject\UserAccountId;
use App\Domain\User\Event\Management\UserDeletedByAdminEvent;
use App\Infrastructure\Messenger\Event\DomainEventLedgerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Désactive le Customer Shop lorsqu'un compte utilisateur est supprimé.
 *
 * Effet en base, donc marquage dans la même transaction.
 */
#[AsMessageHandler(bus: 'event.bus', sign: true)]
final readonly class DisableCustomerHandler
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
        private CommandBusInterface $commandBus,
        private DomainEventLedgerInterface $ledger,
        private TransactionalInterface $transactional,
    ) {
    }

    public function __invoke(UserDeletedByAdminEvent $event): void
    {
        $eventId = $event->eventId();
        $userId = $event->getUserId()->toString();

        $this->transactional->transactional(function () use ($eventId, $userId): void {
            if ($this->ledger->hasProcessed($eventId, self::class)) {
                return;
            }

            $customer = $this->customerRepository->findByUserAccountId(UserAccountId::fromString($userId));

            if (null !== $customer) {
                $this->commandBus->dispatch(new DisableCustomerCommand($customer->getId()->toString()));
            }

            $this->ledger->markProcessed($eventId, self::class);
        });
    }
}
