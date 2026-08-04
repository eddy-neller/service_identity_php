<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Command\Account\RegisterWrongPasswordAttempt;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\ConfigInterface;
use App\Application\Shared\Port\EventDispatcherInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Domain\User\Model\User;
use App\Domain\User\ValueObject\Identity\EmailAddress;

final readonly class RegisterWrongPasswordAttemptCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private ClockInterface $clock,
        private ConfigInterface $config,
        private TransactionalInterface $transactional,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handle(RegisterWrongPasswordAttemptCommand $command): void
    {
        $email = EmailAddress::fromString($command->email);
        $maxAttempts = (int) $this->config->get('app.security.max_login_attempts');

        $user = $this->transactional->transactional(function () use ($email, $maxAttempts): ?User {
            $user = $this->repository->findByEmail($email);

            if (null === $user) {
                return null;
            }

            $user->registerWrongPasswordAttempt($maxAttempts, $this->clock->now());

            $this->repository->save($user);

            return $user;
        });

        if (null !== $user) {
            $this->eventDispatcher->dispatchAll($user->releaseEvents());
        }
    }
}
