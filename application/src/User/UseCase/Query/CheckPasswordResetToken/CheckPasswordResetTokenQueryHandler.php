<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Query\CheckPasswordResetToken;

use App\Application\Shared\CQRS\Query\QueryHandlerInterface;
use App\Application\User\Port\TokenProviderInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Domain\User\ValueObject\EmailAddress;

final readonly class CheckPasswordResetTokenQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private TokenProviderInterface $tokenProvider,
    ) {
    }

    public function handle(CheckPasswordResetTokenQuery $query): CheckPasswordResetTokenOutput
    {
        $split = $this->tokenProvider->split($query->token);
        $email = EmailAddress::fromString($split['email'] ?? '');
        $rawToken = $split['token'] ?? '';

        $user = $this->repository->findByResetPasswordToken($rawToken);

        if (null === $user || !$user->getEmail()->equals($email)) {
            return new CheckPasswordResetTokenOutput(isValid: false);
        }

        $resetPassword = $user->getResetPassword();
        $ttl = $resetPassword->getTokenTtl() ?? 0;
        if ($ttl <= 0 || $ttl <= time()) {
            return new CheckPasswordResetTokenOutput(isValid: false);
        }

        if ($resetPassword->getToken() !== $rawToken) {
            return new CheckPasswordResetTokenOutput(isValid: false);
        }

        return new CheckPasswordResetTokenOutput(isValid: true);
    }
}
