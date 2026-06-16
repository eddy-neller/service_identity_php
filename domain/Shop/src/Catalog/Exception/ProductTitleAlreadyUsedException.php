<?php

declare(strict_types=1);

namespace App\Domain\Shop\Catalog\Exception;

use Throwable;

final class ProductTitleAlreadyUsedException extends CatalogDomainException
{
    public function __construct(
        string $message = 'Product title is already used.',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
