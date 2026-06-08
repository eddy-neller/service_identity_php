<?php

declare(strict_types=1);

namespace App\Domain\Shop\Ordering\Exception;

final class CartLineNotFoundException extends CartDomainException
{
    public function __construct()
    {
        parent::__construct('Cart line not found.');
    }
}
