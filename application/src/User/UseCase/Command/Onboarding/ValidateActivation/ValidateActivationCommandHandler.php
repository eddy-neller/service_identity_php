<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Command\Onboarding\ValidateActivation;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\TokenProviderInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\ValueObject\EmailAddress;

final readonly class ValidateActivationCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private TokenProviderInterface $tokenProvider,
        private ClockInterface $clock,
        private TransactionalInterface $transactional,
    ) {
    }

    public function handle(ValidateActivationCommand $command): void
    {
        $split = $this->tokenProvider->split($command->token);
        $email = EmailAddress::fromString($split['email'] ?? '');
        $rawToken = $split['token'] ?? '';

        $this->transactional->transactional(function () use ($email, $rawToken): void {
            $user = $this->repository->findByActivationToken($rawToken);

            if (null === $user || !$user->getEmail()->equals($email)) {
                throw new UserNotFoundException('User not found for this token.');
            }

            $user->activate($rawToken, $this->clock->now());

            $this->repository->save($user);
        });
    }
}
