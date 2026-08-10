<?php

declare(strict_types=1);

namespace App\Domain\User\Event;

use App\Domain\SharedKernel\Event\DomainEventInterface;
use App\Domain\User\ValueObject\Identity\UserId;

/**
 * Fait métier survenu sur un compte utilisateur.
 *
 * Rend contractuel l'accès au `UserId` — jusqu'ici une simple coïncidence entre les
 * événements du contexte — pour qu'un consommateur puisse réagir à *tout* événement User
 * sans les énumérer un par un.
 */
interface UserDomainEventInterface extends DomainEventInterface
{
    public function getUserId(): UserId;
}
