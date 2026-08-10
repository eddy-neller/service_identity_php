<?php

declare(strict_types=1);

namespace App\Application\User\Port;

use App\Domain\User\Model\User;

interface AccessTokenProviderInterface
{
    /**
     * @return array{token: string, expiresIn: int}
     */
    public function issue(User $user): array;
}
