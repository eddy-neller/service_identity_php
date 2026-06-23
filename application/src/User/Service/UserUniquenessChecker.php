<?php

declare(strict_types=1);

namespace App\Application\User\Service;

use App\Application\User\Port\UserRepositoryInterface;
use App\Application\User\Port\UserUniquenessCheckerInterface;
use App\Domain\User\Exception\Uniqueness\EmailAlreadyUsedException;
use App\Domain\User\Exception\Uniqueness\UsernameAlreadyUsedException;
use App\Domain\User\ValueObject\EmailAddress;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\Username;

final readonly class UserUniquenessChecker implements UserUniquenessCheckerInterface
{
    public function __construct(
        private UserRepositoryInterface $repository,
    ) {
    }

    public function ensureEmailAndUsernameAvailable(EmailAddress $email, Username $username): void
    {
        $existingByEmail = $this->repository->findByEmail($email);
        if (null !== $existingByEmail) {
            throw new EmailAlreadyUsedException();
        }

        $existingByUsername = $this->repository->findByUsername($username);
        if (null !== $existingByUsername) {
            throw new UsernameAlreadyUsedException();
        }
    }

    public function ensureEmailAvailable(EmailAddress $email, ?UserId $excludeUserId = null): void
    {
        $existingByEmail = $this->repository->findByEmail($email);
        if (null === $existingByEmail) {
            return;
        }

        if (null !== $excludeUserId && $existingByEmail->getId()->equals($excludeUserId)) {
            return;
        }

        throw new EmailAlreadyUsedException();
    }

    public function ensureUsernameAvailable(Username $username, ?UserId $excludeUserId = null): void
    {
        $existingByUsername = $this->repository->findByUsername($username);
        if (null === $existingByUsername) {
            return;
        }

        if (null !== $excludeUserId && $existingByUsername->getId()->equals($excludeUserId)) {
            return;
        }

        throw new UsernameAlreadyUsedException();
    }
}
