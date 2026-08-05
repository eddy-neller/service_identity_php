<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Shop\Catalog;

use App\Application\Shop\Port\CategoryRepositoryInterface;
use App\Domain\Shop\Catalog\Model\Category as DomainCategory;
use App\Domain\Shop\Catalog\ValueObject\CategoryId;
use App\Domain\Shop\Catalog\ValueObject\CategoryTitle;
use App\Infrastructure\Entity\Shop\Category as DoctrineCategory;
use App\Infrastructure\Service\Uuid\UuidGeneratorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Gedmo\Tree\Entity\Repository\NestedTreeRepository;
use Ramsey\Uuid\Uuid;

/**
 * @codeCoverageIgnore
 */
final readonly class DoctrineCategoryRepository implements CategoryRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private UuidGeneratorInterface $uuidGenerator,
        private CategoryMapper $mapper,
    ) {
    }

    public function nextIdentity(): CategoryId
    {
        return CategoryId::fromString($this->uuidGenerator->generate());
    }

    /**
     * @return array{items: list<DomainCategory>, totalItems: int, totalPages: int}
     */
    public function list(array $filters, array $orderBy, int $page, int $itemsPerPage): array
    {
        $qb = $this->createQueryBuilder();

        $this->applyFilters($qb, $filters);
        $this->applyOrdering($qb, $orderBy);

        $offset = max(0, ($page - 1) * $itemsPerPage);
        $qb->setFirstResult($offset)->setMaxResults($itemsPerPage);

        $paginator = new Paginator($qb);
        $totalItems = count($paginator);
        $totalPages = $itemsPerPage > 0 ? (int) ceil($totalItems / $itemsPerPage) : 1;

        $categories = [];
        foreach ($paginator as $entity) {
            if ($entity instanceof DoctrineCategory) {
                $categories[] = $this->mapper->toDomain($entity);
            }
        }

        return [
            'items' => $categories,
            'totalItems' => $totalItems,
            'totalPages' => $totalPages,
        ];
    }

    public function save(DomainCategory $category): void
    {
        $entity = $this->findEntity($category->getId());
        $entity = $this->mapper->toDoctrine($category, $entity);

        $parentId = $category->getParentId();
        $entity->setParent(null === $parentId ? null : $this->findEntity($parentId));

        $this->em->persist($entity);
        $this->em->flush();
    }

    public function delete(DomainCategory $category): void
    {
        $entity = $this->findEntity($category->getId());
        if (null === $entity) {
            return;
        }

        $this->em->remove($entity);
        $this->em->flush();
    }

    public function findById(CategoryId $id): ?DomainCategory
    {
        $entity = $this->findEntity($id);

        return null === $entity ? null : $this->mapper->toDomain($entity);
    }

    public function findByTitle(CategoryTitle $title): ?DomainCategory
    {
        $repository = $this->em->getRepository(DoctrineCategory::class);
        $entity = $repository->findOneBy(['title' => $title->toString()]);

        return $entity instanceof DoctrineCategory ? $this->mapper->toDomain($entity) : null;
    }

    /**
     * @return array{category: DomainCategory, parent: ?DomainCategory, children: ?list<DomainCategory>}|null
     */
    public function findTreeById(CategoryId $id): ?array
    {
        $entity = $this->findEntity($id);
        if (null === $entity) {
            return null;
        }

        $parentEntity = $entity->getParent();
        $childrenEntities = $entity->getChildren();

        $parent = null === $parentEntity ? null : $this->mapper->toDomain($parentEntity);
        $children = empty($childrenEntities) ? null : array_map(
            fn (DoctrineCategory $child): DomainCategory => $this->mapper->toDomain($child),
            $childrenEntities,
        );

        return [
            'category' => $this->mapper->toDomain($entity),
            'parent' => $parent,
            'children' => $children,
        ];
    }

    private function findEntity(CategoryId $id): ?DoctrineCategory
    {
        $repository = $this->em->getRepository(DoctrineCategory::class);
        if (!$repository instanceof NestedTreeRepository) {
            return null;
        }

        $entity = $repository->find($id->toString());

        return $entity instanceof DoctrineCategory ? $entity : null;
    }

    private function createQueryBuilder(): QueryBuilder
    {
        $repository = $this->em->getRepository(DoctrineCategory::class);
        if (!$repository instanceof NestedTreeRepository) {
            return $this->em->createQueryBuilder()
                ->select('c')
                ->from(DoctrineCategory::class, 'c');
        }

        return $repository->createQueryBuilder('c');
    }

    private function applyFilters(QueryBuilder $qb, array $filters): void
    {
        $levelValue = filter_var($filters['level'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $level = false === $levelValue ? null : (int) $levelValue;

        if (null !== $level) {
            $qb->andWhere('c.level = :level')
                ->setParameter('level', $level);
        }

        $parent = $filters['parent'] ?? null;
        if (is_string($parent) && Uuid::isValid($parent)) {
            $qb->andWhere('IDENTITY(c.parent) = :parent')
                ->setParameter('parent', $parent);
        }
    }

    private function applyOrdering(QueryBuilder $qb, array $orderBy): void
    {
        $allowedFields = [
            'title' => 'c.title',
            'level' => 'c.level',
            'nbProduct' => 'c.nbProduct',
            'createdAt' => 'c.createdAt',
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
    }
}
