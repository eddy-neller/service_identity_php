<?php

declare(strict_types=1);

namespace App\Application\Shop\Port;

use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Ordering\Model\Cart;
use App\Domain\Shop\Ordering\ValueObject\CartId;
use App\Domain\Shop\Ordering\ValueObject\CartLineId;

interface CartRepositoryInterface
{
    public function nextIdentity(): CartId;

    public function nextLineIdentity(): CartLineId;

    public function findByOwner(CustomerId $ownerId): ?Cart;

    public function findByOwnerForUpdate(CustomerId $ownerId): ?Cart;

    public function save(Cart $cart): void;
}
