<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Shop\Catalog;

use App\Application\Shared\Port\FileInterface;
use App\Application\Shop\Port\ProductRepositoryInterface;
use App\Domain\Shop\Catalog\Model\Category as DomainCategory;
use App\Domain\Shop\Catalog\Model\Product as DomainProduct;
use App\Domain\Shop\Catalog\ValueObject\CategoryId;
use App\Domain\Shop\Catalog\ValueObject\ProductId;
use App\Domain\Shop\Catalog\ValueObject\ProductTitle;
use App\Infrastructure\Adapter\Uuid\UuidGeneratorInterface;
use App\Infrastructure\Persistence\Doctrine\Shop\Catalog\CategoryEntity as DoctrineCategory;
use App\Infrastructure\Persistence\Doctrine\Shop\Catalog\ProductEntity as DoctrineProduct;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Exception;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @codeCoverageIgnore
 */
final readonly class DoctrineProductRepository implements ProductRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private UuidGeneratorInterface $uuidGenerator,
        private ProductMapper $mapper,
        private CategoryMapper $categoryMapper,
    ) {
    }

    public function nextIdentity(): ProductId
    {
        return ProductId::fromString($this->uuidGenerator->generate());
    }

    /**
     * @return array{items: list<array{product: DomainProduct, category: DomainCategory}>, totalItems: int, totalPages: int}
     */
    public function list(array $filters, array $orderBy, int $page, int $itemsPerPage): array
    {
        $qb = $this->createQueryBuilder();

        $this->applyFilters($qb, $filters);
        $this->applyOrdering($qb, $orderBy);

        $offset = max(0, ($page - 1) * $itemsPerPage);
        $qb->setFirstResult($offset)->setMaxResults($itemsPerPage);

        $paginator = new Paginator($qb, false);
        $paginator->setUseOutputWalkers(false);

        $totalItems = count($paginator);
        $totalPages = $itemsPerPage > 0 ? (int) ceil($totalItems / $itemsPerPage) : 1;

        $products = [];
        foreach ($paginator as $entity) {
            if ($entity instanceof DoctrineProduct) {
                $products[] = [
                    'product' => $this->mapper->toDomain($entity),
                    'category' => $this->categoryMapper->toDomain($entity->getCategory()),
                ];
            }
        }

        return [
            'items' => $products,
            'totalItems' => $totalItems,
            'totalPages' => $totalPages,
        ];
    }

    public function save(DomainProduct $product): void
    {
        $entity = $this->findEntity($product->getId());
        $entity = $this->mapper->toDoctrine($product, $entity);

        $category = $this->findCategoryEntity($product->getCategoryId());
        if (null !== $category) {
            $entity->setCategory($category);
        }

        $this->em->persist($entity);
        $this->em->flush();
    }

    public function delete(DomainProduct $product): void
    {
        $entity = $this->findEntity($product->getId());
        if (null === $entity) {
            return;
        }

        $this->em->remove($entity);
        $this->em->flush();
    }

    public function findById(ProductId $id): ?DomainProduct
    {
        $entity = $this->findEntity($id);

        return null === $entity ? null : $this->mapper->toDomain($entity);
    }

    public function findByTitle(ProductTitle $title): ?DomainProduct
    {
        $entity = $this->em->getRepository(DoctrineProduct::class)->findOneBy(['title' => $title->toString()]);

        return $entity instanceof DoctrineProduct ? $this->mapper->toDomain($entity) : null;
    }

    public function findByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $entities = $this->em->getRepository(DoctrineProduct::class)->findBy([
            'id' => array_map(
                static fn (ProductId $id): string => $id->toString(),
                $ids,
            ),
        ]);

        $products = [];
        foreach ($entities as $entity) {
            if ($entity instanceof DoctrineProduct) {
                $products[] = $this->mapper->toDomain($entity);
            }
        }

        return $products;
    }

    public function updateImage(ProductId $id, FileInterface $file): ?DomainProduct
    {
        $entity = $this->findEntity($id);
        if (null === $entity) {
            return null;
        }

        $uploadedFile = new UploadedFile(
            $file->getPathname(),
            $file->getClientOriginalName(),
            '' !== $file->getMimeType() ? $file->getMimeType() : null,
            UPLOAD_ERR_OK,
            true,
        );

        $entity->setImageFile($uploadedFile);
        $this->em->flush();

        return $this->mapper->toDomain($entity);
    }

    private function findEntity(ProductId $id): ?DoctrineProduct
    {
        $entity = $this->em->getRepository(DoctrineProduct::class)->find($id->toString());

        return $entity instanceof DoctrineProduct ? $entity : null;
    }

    private function createQueryBuilder(): QueryBuilder
    {
        return $this->em->createQueryBuilder()
            ->select('p', 'c')
            ->from(DoctrineProduct::class, 'p')
            ->join('p.category', 'c');
    }

    private function applyFilters(QueryBuilder $qb, array $filters): void
    {
        $title = $filters['title'] ?? null;
        if (is_string($title) && '' !== trim($title)) {
            $qb->andWhere('p.title LIKE :title')
                ->setParameter('title', '%' . trim($title) . '%');
        }

        $subtitle = $filters['subtitle'] ?? null;
        if (is_string($subtitle) && '' !== trim($subtitle)) {
            $qb->andWhere('p.subtitle LIKE :subtitle')
                ->setParameter('subtitle', '%' . trim($subtitle) . '%');
        }

        $description = $filters['description'] ?? null;
        if (is_string($description) && '' !== trim($description)) {
            $qb->andWhere('p.description LIKE :description')
                ->setParameter('description', '%' . trim($description) . '%');
        }

        $categoryId = $this->normalizeCategoryFilter($filters['category'] ?? null);
        if (null !== $categoryId) {
            $qb->andWhere('c.id = :categoryId')
                ->setParameter('categoryId', $categoryId);
        }
    }

    private function applyOrdering(QueryBuilder $qb, array $orderBy): void
    {
        $allowedFields = [
            'title' => 'p.title',
            'price' => 'p.price',
            'createdAt' => 'p.createdAt',
            'category.title' => 'c.title',
        ];

        foreach ($orderBy as $field => $direction) {
            if (!isset($allowedFields[$field])) {
                continue;
            }

            $normalizedDirection = strtoupper((string) $direction);
            if (!in_array($normalizedDirection, ['ASC', 'DESC'], true)) {
                $normalizedDirection = 'ASC';
            }

            $qb->addOrderBy($allowedFields[$field], $normalizedDirection);
        }

        $qb->addOrderBy('p.id', 'ASC');
    }

    private function findCategoryEntity(CategoryId $id): ?DoctrineCategory
    {
        $entity = $this->em->getRepository(DoctrineCategory::class)->find($id->toString());

        return $entity instanceof DoctrineCategory ? $entity : null;
    }

    private function normalizeCategoryFilter(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $candidate = trim($value);
        if ('' === $candidate) {
            return null;
        }

        $path = parse_url($candidate, PHP_URL_PATH);
        if (is_string($path) && '' !== $path) {
            $segments = array_values(array_filter(explode('/', trim($path, '/'))));
            if ([] !== $segments) {
                $candidate = end($segments);
            }
        }

        try {
            return CategoryId::fromString($candidate)->toString();
        } catch (Exception) {
            return null;
        }
    }
}
