<?php

declare(strict_types=1);

namespace App\Application\User\Port;

use App\Domain\User\Security\ValueObject\HashedPassword;

interface PasswordHasherInterface
{
    public function hash(string $plainPassword): HashedPassword;

    public function verify(HashedPassword $hashedPassword, string $plainPassword): bool;
}
