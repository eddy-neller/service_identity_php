<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Command\ConfirmPasswordReset;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\PasswordHasherInterface;
use App\Application\User\Port\TokenProviderInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Domain\User\Exception\UserDomainException;
use App\Domain\User\ValueObject\EmailAddress;

final readonly class ConfirmPasswordResetCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private TokenProviderInterface $tokenProvider,
        private PasswordHasherInterface $passwordHasher,
        private ClockInterface $clock,
        private TransactionalInterface $transactional,
    ) {
    }

    public function handle(ConfirmPasswordResetCommand $command): void
    {
        $split = $this->tokenProvider->split($command->token);
        $email = EmailAddress::fromString($split['email'] ?? '');
        $rawToken = $split['token'] ?? '';

        $user = $this->repository->findByResetPasswordToken($rawToken);

        if (null === $user || !$user->getEmail()->equals($email)) {
            throw new UserDomainException('Token de réinitialisation invalide.');
        }

        $hashed = $this->passwordHasher->hash($command->newPassword);

        $this->transactional->transactional(function () use ($user, $hashed, $rawToken): void {
            $now = $this->clock->now();
            $user->completePasswordReset($rawToken, $hashed, $now);

            $this->repository->save($user);
        });
    }
}
