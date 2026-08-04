<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Command\Account\ConfirmPasswordReset;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\EventDispatcherInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\PasswordHasherInterface;
use App\Application\User\Port\TokenProviderInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Domain\User\Exception\UserDomainException;
use App\Domain\User\Model\User;
use App\Domain\User\ValueObject\Identity\EmailAddress;
use App\Domain\User\ValueObject\Security\HashedPassword;

final readonly class ConfirmPasswordResetCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private TokenProviderInterface $tokenProvider,
        private PasswordHasherInterface $passwordHasher,
        private ClockInterface $clock,
        private TransactionalInterface $transactional,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handle(ConfirmPasswordResetCommand $command): void
    {
        $split = $this->tokenProvider->split($command->token);
        $email = EmailAddress::fromString($split['email'] ?? '');
        $rawToken = $split['token'] ?? '';
        $hashed = HashedPassword::fromString($this->passwordHasher->hash($command->newPassword));

        $user = $this->transactional->transactional(function () use ($email, $hashed, $rawToken): User {
            $user = $this->repository->findByResetPasswordToken($rawToken);

            if (null === $user || !$user->getEmail()->equals($email)) {
                throw new UserDomainException('Password reset token is invalid.');
            }

            $user->completePasswordReset($rawToken, $hashed, $this->clock->now());

            $this->repository->save($user);

            return $user;
        });

        $this->eventDispatcher->dispatchAll($user->releaseEvents());
    }
}
