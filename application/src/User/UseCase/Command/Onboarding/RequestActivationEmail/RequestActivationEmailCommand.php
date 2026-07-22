<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Command\Onboarding\RequestActivationEmail;

use App\Application\Shared\CQRS\Command\CommandInterface;

final readonly class RequestActivationEmailCommand implements CommandInterface
{
    public function __construct(
        public string $email,
    ) {
    }
}
