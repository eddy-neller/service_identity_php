<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Query\Ordering\DisplayMyCart;

use App\Application\Shop\ReadModel\Ordering\CartItem;

final readonly class DisplayMyCartOutput
{
    public function __construct(public CartItem $cart)
    {
    }
}
