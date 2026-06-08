<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Ordering\UpdateCartLine;

use App\Application\Shop\ReadModel\Ordering\CartItem;

final readonly class UpdateCartLineOutput
{
    public function __construct(public CartItem $cart)
    {
    }
}
