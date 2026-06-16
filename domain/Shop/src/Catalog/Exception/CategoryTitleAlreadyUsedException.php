<?php

declare(strict_types=1);

namespace App\Domain\Shop\Catalog\Exception;

use Throwable;

final class CategoryTitleAlreadyUsedException extends CatalogDomainException
{
    public function __construct(
        string $message = 'Category title is already used.',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
