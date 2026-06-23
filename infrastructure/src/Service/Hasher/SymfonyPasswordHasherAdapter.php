<?php

declare(strict_types=1);

namespace App\Infrastructure\Service\Hasher;

use App\Application\User\Port\PasswordHasherInterface;
use App\Domain\User\ValueObject\Security\HashedPassword;
use App\Infrastructure\Entity\User\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class SymfonyPasswordHasherAdapter implements PasswordHasherInterface
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function hash(string $plainPassword): HashedPassword
    {
        $user = new User();

        return new HashedPassword(
            $this->passwordHasher->hashPassword($user, $plainPassword)
        );
    }

    public function verify(HashedPassword $hashedPassword, string $plainPassword): bool
    {
        $user = new User();
        $user->setPassword($hashedPassword->toString());

        return $this->passwordHasher->isPasswordValid($user, $plainPassword);
    }
}
