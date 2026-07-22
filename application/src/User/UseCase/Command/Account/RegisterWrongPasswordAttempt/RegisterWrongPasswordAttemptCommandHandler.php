<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Command\Account\RegisterWrongPasswordAttempt;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\ConfigInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Domain\User\ValueObject\Identity\EmailAddress;

final readonly class RegisterWrongPasswordAttemptCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private ClockInterface $clock,
        private ConfigInterface $config,
        private TransactionalInterface $transactional,
    ) {
    }

    public function handle(RegisterWrongPasswordAttemptCommand $command): void
    {
        $email = EmailAddress::fromString($command->email);
        $maxAttempts = (int) $this->config->get('app.security.max_login_attempts');

        $this->transactional->transactional(function () use ($email, $maxAttempts): void {
            $user = $this->repository->findByEmail($email);

            if (null === $user) {
                return;
            }

            $user->registerWrongPasswordAttempt($maxAttempts, $this->clock->now());

            $this->repository->save($user);
        });
    }
}
