<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter\Hasher;

use App\Application\User\Port\PasswordHasherInterface;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity as User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class SymfonyPasswordHasherAdapter implements PasswordHasherInterface
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function hash(string $plainPassword): string
    {
        $user = new User();

        return $this->passwordHasher->hashPassword($user, $plainPassword);
    }

    public function verify(string $hashedPassword, string $plainPassword): bool
    {
        $user = new User();
        $user->setPassword($hashedPassword);

        return $this->passwordHasher->isPasswordValid($user, $plainPassword);
    }
}
