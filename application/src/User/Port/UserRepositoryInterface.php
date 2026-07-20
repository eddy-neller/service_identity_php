<?php

declare(strict_types=1);

namespace App\Application\User\Port;

use App\Application\User\ReadModel\UserList;
use App\Domain\User\Model\User;
use App\Domain\User\ValueObject\EmailAddress;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\Username;

interface UserRepositoryInterface
{
    public const array SORT_FIELDS = ['username', 'email', 'createdAt'];

    public function nextIdentity(): UserId;

    public function list(array $filters, array $orderBy, int $page, int $itemsPerPage): UserList;

    public function save(User $user): void;

    public function delete(User $user): void;

    public function findById(UserId $id): ?User;

    public function findByUsername(Username $username): ?User;

    public function findByEmail(EmailAddress $email): ?User;

    public function findByActivationToken(string $token): ?User;

    public function findByResetPasswordToken(string $token): ?User;
}
