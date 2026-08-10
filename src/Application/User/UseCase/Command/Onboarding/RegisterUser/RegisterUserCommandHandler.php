<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Command\Onboarding\RegisterUser;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\DateIntervalTrait;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\ConfigInterface;
use App\Application\Shared\Port\DomainEventBusInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\PasswordHasherInterface;
use App\Application\User\Port\TokenProviderInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Application\User\Port\UserUniquenessCheckerInterface;
use App\Application\User\ReadModel\UserItem;
use App\Domain\User\Model\User;
use App\Domain\User\ValueObject\Identity\EmailAddress;
use App\Domain\User\ValueObject\Identity\Username;
use App\Domain\User\ValueObject\Profile\Preferences;
use App\Domain\User\ValueObject\Security\HashedPassword;

final readonly class RegisterUserCommandHandler implements CommandHandlerInterface
{
    use DateIntervalTrait;

    public function __construct(
        private UserRepositoryInterface $repository,
        private PasswordHasherInterface $passwordHasher,
        private TokenProviderInterface $tokenProvider,
        private ClockInterface $clock,
        private TransactionalInterface $transactional,
        private ConfigInterface $config,
        private UserUniquenessCheckerInterface $uniquenessChecker,
        private DomainEventBusInterface $eventBus,
    ) {
    }

    public function handle(RegisterUserCommand $command): UserItem
    {
        $username = Username::fromString($command->username);
        $email = EmailAddress::fromString($command->email);
        $preferences = Preferences::fromArray($command->preferences ?? []);

        // Avant le hash : bcrypt coûte ~390 ms qu'il est inutile de payer pour une
        // inscription qui repartira en 409. Ce contrôle ne fait pas foi — la course
        // entre lui et l'INSERT est tranchée par l'index unique en base.
        $this->uniquenessChecker->ensureEmailAndUsernameAvailable($email, $username);

        $hashedPassword = HashedPassword::fromString($this->passwordHasher->hash($command->plainPassword));

        $now = $this->clock->now();
        $user = User::register(
            id: $this->repository->nextIdentity(),
            username: $username,
            email: $email,
            password: $hashedPassword,
            preferences: $preferences,
            now: $now,
        );
        $token = $this->tokenProvider->generateRandomToken();
        $expiredAt = $now->add($this->createInterval($this->config->getString('register_token_ttl', 'P2D')));

        $user->requestActivation($token, $expiredAt, $now);

        $user = $this->transactional->transactional(function () use ($user): User {
            $this->repository->add($user);
            $this->eventBus->publishAll($user->releaseEvents());

            return $user;
        });

        return UserItem::fromUser($user);
    }
}
