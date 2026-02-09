<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Shop\Customer;

use App\Application\Shared\Port\UuidGeneratorInterface;
use App\Application\Shop\Port\CustomerRepositoryInterface;
use App\Application\Shop\ReadModel\CustomerList;
use App\Domain\Shop\Customer\Model\Customer as DomainCustomer;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Customer\ValueObject\CustomerStatus;
use App\Domain\Shop\Customer\ValueObject\UserAccountId;
use App\Infrastructure\Entity\Shop\Customer as DoctrineCustomer;
use App\Infrastructure\Entity\User\User as DoctrineUser;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Ramsey\Uuid\Uuid;

/**
 * @codeCoverageIgnore
 */
final readonly class DoctrineCustomerRepository implements CustomerRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private UuidGeneratorInterface $uuidGenerator,
        private CustomerMapper $mapper,
    ) {
    }

    public function nextIdentity(): CustomerId
    {
        return CustomerId::fromString($this->uuidGenerator->generate());
    }

    public function list(array $filters, array $orderBy, int $page, int $itemsPerPage): CustomerList
    {
        $qb = $this->em->createQueryBuilder()
            ->select('c')
            ->from(DoctrineCustomer::class, 'c');

        $this->applyFilters($qb, $filters);
        $this->applyOrdering($qb, $orderBy);

        $offset = max(0, ($page - 1) * $itemsPerPage);
        $qb->setFirstResult($offset)->setMaxResults($itemsPerPage);

        $paginator = new Paginator($qb);
        $totalItems = count($paginator);
        $totalPages = $itemsPerPage > 0 ? (int) ceil($totalItems / $itemsPerPage) : 1;

        $customers = [];
        foreach ($paginator as $entity) {
            if ($entity instanceof DoctrineCustomer) {
                $customers[] = $this->mapper->toDomain($entity);
            }
        }

        return new CustomerList(
            customers: $customers,
            totalItems: $totalItems,
            totalPages: $totalPages,
        );
    }

    public function save(DomainCustomer $customer): void
    {
        $entity = $this->findEntity($customer->getId());
        $entity = $this->mapper->toDoctrine($customer, $entity);

        $this->em->persist($entity);
        $this->em->flush();
    }

    public function delete(DomainCustomer $customer): void
    {
        $entity = $this->findEntity($customer->getId());
        if (null === $entity) {
            return;
        }

        $this->em->remove($entity);
        $this->em->flush();
    }

    public function findById(CustomerId $id): ?DomainCustomer
    {
        $entity = $this->findEntity($id);

        return $entity instanceof DoctrineCustomer ? $this->mapper->toDomain($entity) : null;
    }

    public function findByUserAccountId(UserAccountId $userAccountId): ?DomainCustomer
    {
        $repository = $this->em->getRepository(DoctrineCustomer::class);
        $entity = $repository->findOneBy(['userAccountId' => Uuid::fromString($userAccountId->toString())]);

        return $entity instanceof DoctrineCustomer ? $this->mapper->toDomain($entity) : null;
    }

    private function findEntity(CustomerId $id): ?DoctrineCustomer
    {
        $entity = $this->em->find(DoctrineCustomer::class, $id->toString());

        return $entity instanceof DoctrineCustomer ? $entity : null;
    }

    private function applyFilters(QueryBuilder $qb, array $filters): void
    {
        $userAccountId = $this->resolveUserAccountId($filters['userAccountId'] ?? null);
        if (null !== $userAccountId) {
            $qb->andWhere('c.userAccountId = :userAccountId')
                ->setParameter('userAccountId', Uuid::fromString($userAccountId->toString()));
        }

        $status = $this->resolveStatus($filters['status'] ?? null);
        if (null !== $status) {
            $qb->andWhere('c.status = :status')
                ->setParameter('status', $status->toInt());
        }
    }

    private function resolveStatus(mixed $status): ?CustomerStatus
    {
        $statusValue = filter_var($status, FILTER_VALIDATE_INT);
        if (false === $statusValue) {
            return null;
        }

        $statusValue = (int) $statusValue;
        if (!in_array($statusValue, [CustomerStatus::ACTIVE, CustomerStatus::DISABLED], true)) {
            return null;
        }

        return CustomerStatus::fromInt($statusValue);
    }

    private function resolveUserAccountId(mixed $userAccountId): ?UserAccountId
    {
        if (!is_string($userAccountId)) {
            return null;
        }

        $trimmed = trim($userAccountId);
        if ('' === $trimmed) {
            return null;
        }

        try {
            return UserAccountId::fromString($trimmed);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    private function applyOrdering(QueryBuilder $qb, array $orderBy): void
    {
        if (array_key_exists('username', $orderBy) && !in_array('u', $qb->getAllAliases(), true)) {
            $qb->leftJoin(DoctrineUser::class, 'u', 'WITH', 'u.id = c.userAccountId');
        }

        $allowedFields = [
            'createdAt' => 'c.createdAt',
            'status' => 'c.status',
            'username' => 'u.username',
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
