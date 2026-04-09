<?php

declare(strict_types=1);

namespace App\Entity;

use App\Infrastructure\Entity\User\User;

interface HasOwnerInterface
{
    public function setUser(User $user): self;

    public function getUser(): User;
}
