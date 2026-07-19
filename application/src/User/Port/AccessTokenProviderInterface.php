<?php

declare(strict_types=1);

namespace App\Application\User\Port;

use App\Application\User\ReadModel\IssuedAccessToken;
use App\Domain\User\Model\User;

interface AccessTokenProviderInterface
{
    public function issue(User $user): IssuedAccessToken;
}
