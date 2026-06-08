<?php

declare(strict_types=1);

namespace App\Domain\Shop\Ordering\Exception;

final class CartQuantityExceededException extends CartDomainException
{
    public function __construct()
    {
        parent::__construct('Cart line quantity must be between 1 and 99.');
    }
}
