<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Query\CheckPasswordResetToken;

use App\Application\Shared\CQRS\Query\QueryHandlerInterface;
use App\Application\User\Port\TokenProviderInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Domain\User\ValueObject\EmailAddress;
use Exception;

final readonly class CheckPasswordResetTokenQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private TokenProviderInterface $tokenProvider,
    ) {
    }

    public function handle(CheckPasswordResetTokenQuery $query): bool
    {
        $split = $this->tokenProvider->split($query->token);
        $rawToken = $split['token'] ?? '';

        try {
            $email = EmailAddress::fromString($split['email'] ?? '');
        } catch (Exception) {
            return false;
        }

        $user = $this->repository->findByResetPasswordToken($rawToken);

        if (null === $user || !$user->getEmail()->equals($email)) {
            return false;
        }

        $resetPassword = $user->getResetPassword();
        $ttl = $resetPassword->getTokenTtl() ?? 0;
        if ($ttl <= 0 || $ttl <= time()) {
            return false;
        }

        return $resetPassword->getToken() === $rawToken;
    }
}
