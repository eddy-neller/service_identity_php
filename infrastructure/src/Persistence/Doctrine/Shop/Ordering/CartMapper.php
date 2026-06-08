<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Shop\Ordering;

use App\Domain\Shop\Catalog\ValueObject\ProductId;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Ordering\Model\Cart as DomainCart;
use App\Domain\Shop\Ordering\Model\CartLine as DomainCartLine;
use App\Domain\Shop\Ordering\ValueObject\CartId;
use App\Domain\Shop\Ordering\ValueObject\CartLineId;
use App\Infrastructure\Entity\Shop\Cart as DoctrineCart;

final readonly class CartMapper
{
    public function toDomain(DoctrineCart $entity): DomainCart
    {
        $lines = [];

        foreach ($entity->getLines() as $line) {
            $lines[] = DomainCartLine::create(
                CartLineId::fromString($line->getId()->toString()),
                ProductId::fromString($line->getProduct()->getId()->toString()),
                $line->getQuantity(),
            );
        }

        return DomainCart::reconstitute(
            CartId::fromString($entity->getId()->toString()),
            CustomerId::fromString($entity->getCustomer()->getId()->toString()),
            $lines,
            $entity->getCreatedAt(),
            $entity->getUpdatedAt(),
        );
    }
}
