<?php

declare(strict_types=1);

namespace App\Domain\Shop\Ordering\Model;

use App\Domain\Shop\Catalog\ValueObject\ProductId;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Ordering\Exception\CartLineNotFoundException;
use App\Domain\Shop\Ordering\ValueObject\CartId;
use App\Domain\Shop\Ordering\ValueObject\CartLineId;
use DateTimeImmutable;

final class Cart
{
    /** @param CartLine[] $lines */
    private function __construct(
        private readonly CartId $id,
        private readonly CustomerId $ownerId,
        private array $lines,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {
    }

    public static function create(CartId $id, CustomerId $ownerId, DateTimeImmutable $now): self
    {
        return new self($id, $ownerId, [], $now, $now);
    }

    /** @param CartLine[] $lines */
    public static function reconstitute(
        CartId $id,
        CustomerId $ownerId,
        array $lines,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ): self {
        return new self($id, $ownerId, $lines, $createdAt, $updatedAt);
    }

    public function addLine(
        CartLineId $lineId,
        ProductId $productId,
        int $quantity,
        DateTimeImmutable $now,
    ): void {
        $line = $this->findLineByProduct($productId);

        if (null !== $line) {
            $line->increase($quantity);
        } else {
            $this->lines[] = CartLine::create($lineId, $productId, $quantity);
        }

        $this->touch($now);
    }

    public function changeLineQuantity(ProductId $productId, int $quantity, DateTimeImmutable $now): void
    {
        if (0 === $quantity) {
            $this->removeLine($productId, $now);

            return;
        }

        $this->updateLine($productId, $quantity, $now);
    }

    public function updateLine(ProductId $productId, int $quantity, DateTimeImmutable $now): void
    {
        $this->getLineByProduct($productId)->setQuantity($quantity);
        $this->touch($now);
    }

    public function removeLine(ProductId $productId, DateTimeImmutable $now): void
    {
        $line = $this->getLineByProduct($productId);
        $this->lines = array_values(array_filter(
            $this->lines,
            static fn (CartLine $candidate): bool => !$candidate->getId()->equals($line->getId()),
        ));
        $this->touch($now);
    }

    public function clear(DateTimeImmutable $now): void
    {
        $this->lines = [];
        $this->touch($now);
    }

    /** @return CartLine[] */
    public function getLines(): array
    {
        return $this->lines;
    }

    public function getId(): CartId
    {
        return $this->id;
    }

    public function getOwnerId(): CustomerId
    {
        return $this->ownerId;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function getLineByProduct(ProductId $productId): CartLine
    {
        return $this->findLineByProduct($productId) ?? throw new CartLineNotFoundException();
    }

    private function findLineByProduct(ProductId $productId): ?CartLine
    {
        foreach ($this->lines as $line) {
            if ($line->getProductId()->equals($productId)) {
                return $line;
            }
        }

        return null;
    }

    private function touch(DateTimeImmutable $now): void
    {
        $this->updatedAt = $now;
    }
}
