<?php

namespace App\Entity\Shop;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model;
use App\Domain\User\Security\ValueObject\RoleSet;
use App\Infrastructure\Entity\User\User;
use App\Presentation\RouteRequirements;
use App\Presentation\Shared\State\PaginatedCollectionProvider;
use App\Repository\Shop\OrderRepository;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: 'shop_order')]
#[ORM\Index(name: 'ShopOrderUserIdx', columns: ['user_id'])]
#[ORM\Index(name: 'ShopOrderIsPaidIdx', columns: ['is_paid'])]
#[ORM\Index(name: 'ShopOrderStripeSessionIdx', columns: ['stripe_session_id'])]
#[ORM\UniqueConstraint(name: 'ShopOrderReferenceUniq', columns: ['reference'])]
#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ApiResource(
    shortName: 'ShopOrder',
    operations: [
        new Get(
            uriTemplate: '/orders/{id}',
            requirements: ['id' => RouteRequirements::UUID],
            openapi: new Model\Operation(
                security: [['JWT' => []]]
            ),
            security: "is_granted('shop_order:item:read', object)",
            name: self::PREFIX_NAME . 'get',
        ),
        new Patch(
            uriTemplate: '/orders/{id}',
            requirements: ['id' => RouteRequirements::UUID],
            openapi: new Model\Operation(
                security: [['JWT' => []]]
            ),
            security: "is_granted('shop_order:item:write', object)",
            name: self::PREFIX_NAME . 'patch',
        ),
        new Delete(
            uriTemplate: '/orders/{id}',
            requirements: ['id' => RouteRequirements::UUID],
            openapi: new Model\Operation(
                security: [['JWT' => []]]
            ),
            security: "is_granted('" . RoleSet::ROLE_ADMIN . "')",
            name: self::PREFIX_NAME . 'delete',
        ),
        new GetCollection(
            uriTemplate: '/orders',
            openapi: new Model\Operation(
                security: [['JWT' => []]]
            ),
            paginationClientItemsPerPage: true,
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            name: self::PREFIX_NAME . 'col',
            provider: PaginatedCollectionProvider::class,
        ),
        new GetCollection(
            uriTemplate: '/orders',
            openapi: new Model\Operation(
                security: [['JWT' => []]]
            ),
            paginationClientItemsPerPage: true,
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            name: self::PREFIX_NAME . 'me_col',
            provider: PaginatedCollectionProvider::class,
        ),
    ],
    routePrefix: '/shop/me',
    order: ['createdAt' => 'DESC']
)]
#[ApiFilter(filterClass: BooleanFilter::class, properties: ['isPaid'])]
#[ApiFilter(filterClass: OrderFilter::class, properties: ['createdAt', 'reference'])]
class Order
{
    private const string PREFIX_NAME = 'shop-orders-';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[Groups(['shop_order:read'])]
    protected UuidInterface $id;

    #[Groups(['shop_order:read'])]
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[Groups(['shop_order:read'])]
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Gedmo\Timestampable(on: 'create')]
    private DateTimeInterface $createdAt;

    /**
     * Choix de conception : on ne crée pas de relation avec l'entité Carrier,
     * mais on stocke le nom et le prix du transporteur dans la table OrderDetails.
     * Car, si l'on décide de modifier le nom ou le prix du transporteur,
     * l'information de la commande sera erronée.
     * Regarder la vidéo 043 Création de l'entité Order() et OrderDetails().mp4 à 2m45.
     */
    #[Groups(['shop_order:read'])]
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $carrierName;

    #[Groups(['shop_order:read'])]
    #[ORM\Column(type: Types::FLOAT)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    private float $carrierPrice;

    /**
     * Choix de conception : Idem ici avec l'adresse de livraison.
     */
    #[Groups(['shop_order:read'])]
    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $delivery;

    #[Groups(['shop_order:read'])]
    #[ORM\OneToMany(targetEntity: OrderDetails::class, mappedBy: 'order', cascade: ['persist'])]
    private Collection $orderDetails;

    #[Groups(['shop_order:read'])]
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isPaid = false;

    #[Groups(['shop_order:read'])]
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $reference;

    #[Groups(['shop_order:read'])]
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $stripeSessionId = null;

    #[Groups(['shop_order:read'])]
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Gedmo\Timestampable(on: 'update')]
    private DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->orderDetails = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->getReference();
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function setId(UuidInterface $id): void
    {
        $this->id = $id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCarrierName(): ?string
    {
        return $this->carrierName;
    }

    public function setCarrierName(string $carrierName): self
    {
        $this->carrierName = $carrierName;

        return $this;
    }

    public function getCarrierPrice(): ?float
    {
        return $this->carrierPrice;
    }

    public function setCarrierPrice(float $carrierPrice): self
    {
        $this->carrierPrice = $carrierPrice;

        return $this;
    }

    public function getDelivery(): ?string
    {
        return $this->delivery;
    }

    public function setDelivery(string $delivery): self
    {
        $this->delivery = $delivery;

        return $this;
    }

    /**
     * @return OrderDetails[]
     */
    public function getOrderDetails(): array
    {
        return $this->orderDetails->getValues();
    }

    public function addOrderDetail(OrderDetails $orderDetail): self
    {
        if (!$this->orderDetails->contains($orderDetail)) {
            $this->orderDetails[] = $orderDetail;
            $orderDetail->setOrder($this);
        }

        return $this;
    }

    public function removeOrderDetail(OrderDetails $orderDetail): self
    {
        if ($this->orderDetails->removeElement($orderDetail) && $orderDetail->getOrder() === $this) {
            $orderDetail->setOrder(null);
        }

        return $this;
    }

    public function getIsPaid(): ?bool
    {
        return $this->isPaid;
    }

    public function setIsPaid(bool $isPaid): self
    {
        $this->isPaid = $isPaid;

        return $this;
    }

    public function getTotal(): ?float
    {
        $total = null;
        foreach ($this->getOrderDetails() as $product) {
            $total += ($product->getPrice() * $product->getQuantity());
        }

        return $total;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    public function getStripeSessionId(): ?string
    {
        return $this->stripeSessionId;
    }

    public function setStripeSessionId(?string $stripeSessionId): self
    {
        $this->stripeSessionId = $stripeSessionId;

        return $this;
    }

    public function getUpdatedAt(): DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeInterface $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
