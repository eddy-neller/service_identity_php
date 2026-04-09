<?php

declare(strict_types=1);

namespace App\Presentation\Shop\Validator\Catalog;

use Symfony\Component\Validator\Constraint;

#[\Attribute] class ShopCategoryNotExists extends Constraint
{
    public string $message = 'A shop category already has this title.';

    public const string SAME_TITLE_ERROR = 'f5fc81d7-b7ae-44db-bd4d-1c39f3643807';

    protected const array ERROR_NAMES = [
        self::SAME_TITLE_ERROR => 'SAME_TITLE_ERROR',
    ];
}
