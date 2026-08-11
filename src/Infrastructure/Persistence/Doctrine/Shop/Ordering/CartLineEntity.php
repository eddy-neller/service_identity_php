<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Shop\Ordering;

use App\Infrastructure\Persistence\Doctrine\Shop\Catalog\ProductEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

#[ORM\Table(name: 'shop_cart_line')]
#[ORM\UniqueConstraint(name: 'ShopCartLineProductUniq', columns: ['cart_id', 'product_id'])]
#[ORM\Index(name: 'ShopCartLineCartIdx', columns: ['cart_id'])]
#[ORM\Index(name: 'ShopCartLineProductIdx', columns: ['product_id'])]
#[ORM\Entity]
class CartLineEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: CartEntity::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(name: 'cart_id', nullable: false, onDelete: 'CASCADE')]
    private CartEntity $cart;

    #[ORM\ManyToOne(targetEntity: ProductEntity::class)]
    #[ORM\JoinColumn(name: 'product_id', nullable: false, onDelete: 'CASCADE')]
    private ProductEntity $product;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $quantity;

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function setId(UuidInterface $id): void
    {
        $this->id = $id;
    }

    public function getCart(): CartEntity
    {
        return $this->cart;
    }

    public function setCart(CartEntity $cart): void
    {
        $this->cart = $cart;
    }

    public function getProduct(): ProductEntity
    {
        return $this->product;
    }

    public function setProduct(ProductEntity $product): void
    {
        $this->product = $product;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }
}
