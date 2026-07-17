<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Command\RequestPasswordReset;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\DateIntervalTrait;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\ConfigInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\TokenProviderInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Domain\User\ValueObject\EmailAddress;

final readonly class RequestPasswordResetCommandHandler implements CommandHandlerInterface
{
    use DateIntervalTrait;

    public function __construct(
        private UserRepositoryInterface $repository,
        private TokenProviderInterface $tokenProvider,
        private ClockInterface $clock,
        private TransactionalInterface $transactional,
        private ConfigInterface $config,
    ) {
    }

    public function handle(RequestPasswordResetCommand $command): void
    {
        $email = EmailAddress::fromString($command->email);
        $token = $this->tokenProvider->generateRandomToken();
        $resetPasswordInterval = $this->createInterval($this->config->getString('reset_password_token_ttl', 'PT15M'));

        $this->transactional->transactional(function () use ($email, $token, $resetPasswordInterval): void {
            $user = $this->repository->findByEmail($email);

            if (null === $user) {
                return;
            }

            $now = $this->clock->now();
            $expiresAt = $now->add($resetPasswordInterval);

            $user->requestPasswordReset($token, $expiresAt, $now);

            $this->repository->save($user);
        });
    }
}
