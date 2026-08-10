<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Command\Onboarding\RegisterUser;

use App\Application\Shared\CQRS\Command\CommandInterface;

/**
 * @param array{lang?: string}|null $preferences
 */
final readonly class RegisterUserCommand implements CommandInterface
{
    public function __construct(
        public string $email,
        public string $username,
        public string $plainPassword,
        public ?array $preferences = null,
    ) {
    }
}
