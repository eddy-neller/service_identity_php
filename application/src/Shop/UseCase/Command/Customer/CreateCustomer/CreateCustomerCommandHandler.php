<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Customer\CreateCustomer;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\CustomerRepositoryInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Domain\Shop\Customer\Exception\CustomerAlreadyExistsException;
use App\Domain\Shop\Customer\Model\Customer;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\ValueObject\UserId;

final readonly class CreateCustomerCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private CustomerRepositoryInterface $repository,
        private UserRepositoryInterface $userRepository,
        private ClockInterface $clock,
        private TransactionalInterface $transactional,
    ) {
    }

    public function handle(CreateCustomerCommand $command): CreateCustomerOutput
    {
        return $this->transactional->transactional(function () use ($command): CreateCustomerOutput {
            $userId = UserId::fromString($command->userAccountId->toString());
            $user = $this->userRepository->findById($userId);

            if (null === $user) {
                throw new UserNotFoundException();
            }

            $customer = $this->repository->findByUserAccountId($command->userAccountId);

            if (null !== $customer) {
                throw new CustomerAlreadyExistsException();
            }

            $customer = Customer::create(
                id: $this->repository->nextIdentity(),
                now: $this->clock->now(),
                userAccountId: $command->userAccountId,
            );

            $this->repository->save($customer);

            return new CreateCustomerOutput($customer);
        });
    }
}
