<?php

declare(strict_types=1);

namespace App\Entity;

use App\Infrastructure\Persistence\Doctrine\User\UserEntity as User;

interface HasOwnerInterface
{
    public function setUser(User $user): self;

    public function getUser(): User;
}
