<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Shop\Ordering;

use App\Infrastructure\Persistence\Doctrine\Shop\Customer\CustomerEntity;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

#[ORM\Table(name: 'shop_cart')]
#[ORM\UniqueConstraint(name: 'ShopCartCustomerUniq', columns: ['customer_id'])]
#[ORM\Entity]
class CartEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\OneToOne(targetEntity: CustomerEntity::class)]
    #[ORM\JoinColumn(name: 'customer_id', unique: true, nullable: false, onDelete: 'CASCADE')]
    private CustomerEntity $customer;

    /** @var Collection<int, CartLineEntity> */
    #[ORM\OneToMany(
        targetEntity: CartLineEntity::class,
        mappedBy: 'cart',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    private Collection $lines;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->lines = new ArrayCollection();
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function setId(UuidInterface $id): void
    {
        $this->id = $id;
    }

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    public function setCustomer(CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }

    /** @return Collection<int, CartLineEntity> */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(CartLineEntity $line): void
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setCart($this);
        }
    }

    public function removeLine(CartLineEntity $line): void
    {
        $this->lines->removeElement($line);
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
