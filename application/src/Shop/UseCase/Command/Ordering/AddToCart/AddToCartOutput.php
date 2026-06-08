<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Ordering\AddToCart;

use App\Application\Shop\ReadModel\Ordering\CartItem;

final readonly class AddToCartOutput
{
    public function __construct(public CartItem $cart)
    {
    }
}
