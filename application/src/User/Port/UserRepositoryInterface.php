<?php

declare(strict_types=1);

namespace App\Application\User\Port;

use App\Domain\User\Model\User;
use App\Domain\User\ValueObject\Identity\EmailAddress;
use App\Domain\User\ValueObject\Identity\UserId;
use App\Domain\User\ValueObject\Identity\Username;

interface UserRepositoryInterface
{
    public const array SORT_FIELDS = ['username', 'email', 'createdAt'];

    public function nextIdentity(): UserId;

    public function list(array $filters, array $orderBy, int $page, int $itemsPerPage): array;

    public function add(User $user): void;

    public function save(User $user): void;

    public function delete(User $user): void;

    public function findById(UserId $id): ?User;

    public function findByUsername(Username $username): ?User;

    public function findByEmail(EmailAddress $email): ?User;

    public function findByActivationToken(string $token): ?User;

    public function findByResetPasswordToken(string $token): ?User;
}
