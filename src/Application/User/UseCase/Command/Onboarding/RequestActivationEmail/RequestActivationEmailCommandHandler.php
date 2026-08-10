<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Command\Onboarding\RequestActivationEmail;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\DateIntervalTrait;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\ConfigInterface;
use App\Application\Shared\Port\DomainEventBusInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\TokenProviderInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Domain\User\ValueObject\Identity\EmailAddress;

final readonly class RequestActivationEmailCommandHandler implements CommandHandlerInterface
{
    use DateIntervalTrait;

    public function __construct(
        private UserRepositoryInterface $repository,
        private TokenProviderInterface $tokenProvider,
        private ClockInterface $clock,
        private TransactionalInterface $transactional,
        private ConfigInterface $config,
        private DomainEventBusInterface $eventBus,
    ) {
    }

    public function handle(RequestActivationEmailCommand $command): void
    {
        $email = EmailAddress::fromString($command->email);
        $token = $this->tokenProvider->generateRandomToken();
        $now = $this->clock->now();
        $expiredAt = $now->add($this->createInterval($this->config->getString('register_token_ttl', 'P2D')));

        $this->transactional->transactional(function () use ($email, $token, $expiredAt, $now): void {
            $user = $this->repository->findByEmail($email);

            if (null === $user) {
                return;
            }

            $user->requestActivation($token, $expiredAt, $now);

            $this->repository->save($user);
            $this->eventBus->publishAll($user->releaseEvents());
        });
    }
}
