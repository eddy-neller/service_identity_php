<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Command\RegisterWrongPasswordAttempt;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\ConfigInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Domain\User\ValueObject\EmailAddress;

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
        $user = $this->repository->findByEmail($email);

        if (null === $user) {
            return;
        }

        $this->transactional->transactional(function () use ($user): void {
            $now = $this->clock->now();
            $maxAttempts = (int) $this->config->get('app.security.max_login_attempts');
            $user->registerWrongPasswordAttempt($maxAttempts, $now);

            $this->repository->save($user);
        });
    }
}
