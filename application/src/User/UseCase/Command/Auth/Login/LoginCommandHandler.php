<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Command\Auth\Login;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\ConfigInterface;
use App\Application\Shared\Port\DomainEventBusInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\PasswordHasherInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Application\User\ReadModel\AuthTokens;
use App\Application\User\Service\AuthTokenIssuer;
use App\Domain\User\Exception\Lifecycle\AccountNotActivatedException;
use App\Domain\User\Exception\Security\InvalidCredentialsException;
use App\Domain\User\Exception\Security\UserLockedException;
use App\Domain\User\ValueObject\Identity\EmailAddress;

final readonly class LoginCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private PasswordHasherInterface $passwordHasher,
        private AuthTokenIssuer $tokenIssuer,
        private ClockInterface $clock,
        private ConfigInterface $config,
        private TransactionalInterface $transactional,
        private DomainEventBusInterface $eventBus,
    ) {
    }

    public function handle(LoginCommand $command): AuthTokens
    {
        $email = EmailAddress::fromString($command->email);
        $now = $this->clock->now();
        $maxAttempts = (int) $this->config->get('app.security.max_login_attempts');

        $user = $this->repository->findByEmail($email);

        if (null === $user) {
            throw new InvalidCredentialsException();
        }

        if ($user->isLocked()) {
            throw new UserLockedException();
        }

        $passwordValid = $this->passwordHasher->verify($user->getPassword()->toString(), $command->password);

        if (!$passwordValid) {
            $locked = $this->transactional->transactional(function () use ($user, $maxAttempts, $now): bool {
                $user->registerWrongPasswordAttempt($maxAttempts, $now);
                $this->repository->save($user);
                $this->eventBus->publishAll($user->releaseEvents());

                return $user->isLocked();
            });

            if ($locked) {
                throw new UserLockedException();
            }

            throw new InvalidCredentialsException();
        }

        if (!$user->isActive()) {
            throw new AccountNotActivatedException();
        }

        return $this->transactional->transactional(function () use ($user, $now): AuthTokens {
            $user->resetWrongPasswordAttempts($now);
            $user->recordSuccessfulLogin($now);

            $this->repository->save($user);
            $this->eventBus->publishAll($user->releaseEvents());

            return $this->tokenIssuer->issue($user, $now);
        });
    }
}
