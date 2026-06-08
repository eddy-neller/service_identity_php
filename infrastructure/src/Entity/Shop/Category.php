<?php

declare(strict_types=1);

namespace App\Infrastructure\Entity\Shop;

use App\Infrastructure\Persistence\Doctrine\Shop\NestedTreeRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Tree\Traits\NestedSetEntityUuid;
use Ramsey\Uuid\UuidInterface;

#[ORM\Table(name: 'shop_category')]
#[ORM\Index(name: 'ShopCategoryTitleIdx', columns: ['title'])]
#[ORM\Index(name: 'ShopCategoryNbProductIdx', columns: ['nb_product'])]
#[ORM\Index(name: 'ShopCategoryLevelIdx', columns: ['lvl'])]
#[ORM\Index(name: 'ShopCategoryParentIdx', columns: ['parent_id'])]
#[ORM\Index(name: 'ShopCategoryCreatedAtIdx', columns: ['created_at'])]
#[ORM\UniqueConstraint(name: 'ShopCategorySlugUniq', columns: ['slug'])]
#[ORM\Entity(repositoryClass: NestedTreeRepository::class)]
#[Gedmo\Tree(type: 'nested')]
class Category
{
    use NestedSetEntityUuid;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    protected UuidInterface $id;

    #[ORM\Column(type: Types::STRING)]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $nbProduct = 0;

    #[ORM\Column(length: 128)]
    private string $slug;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', onDelete: 'CASCADE')]
    #[Gedmo\TreeParent]
    private ?Category $parent = null;

    #[ORM\OneToMany(targetEntity: Category::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['left' => 'ASC'])]
    private Collection $children;

    #[ORM\OneToMany(targetEntity: Product::class, mappedBy: 'category', cascade: ['persist', 'remove'])]
    private Collection $products;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Gedmo\Timestampable(on: 'create')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Gedmo\Timestampable(on: 'update')]
    private DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->products = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->title;
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function setId(UuidInterface $id): void
    {
        $this->id = $id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getNbProduct(): int
    {
        return $this->nbProduct;
    }

    public function setNbProduct(int $nbProduct): self
    {
        $this->nbProduct = $nbProduct;

        return $this;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): self
    {
        $this->parent = $parent;

        return $this;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function setLevel(int $level): self
    {
        $this->level = $level;

        return $this;
    }

    public function hasChildren(): bool
    {
        return $this->right > $this->left + 1;
    }

    /**
     * @return Category[]
     */
    public function getChildren(): array
    {
        return $this->children->getValues();
    }

    /**
     * @return Product[]
     */
    public function getProducts(): array
    {
        return $this->products->getValues();
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

    public function increaseNbProduct(): void
    {
        ++$this->nbProduct;
    }

    public function decreaseNbProduct(): void
    {
        --$this->nbProduct;
    }
}
