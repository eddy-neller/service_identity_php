<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Shop\Customer;

use App\Application\Shared\Port\UuidGeneratorInterface;
use App\Application\Shared\ReadModel\Pagination;
use App\Application\Shop\Port\AddressRepositoryInterface;
use App\Application\Shop\ReadModel\AddressList;
use App\Domain\Shop\Customer\Model\Address as DomainAddress;
use App\Domain\Shop\Customer\ValueObject\AddressId;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Infrastructure\Entity\Shop\Address as DoctrineAddress;
use App\Infrastructure\Entity\Shop\Customer as DoctrineCustomer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @codeCoverageIgnore
 */
final readonly class DoctrineAddressRepository implements AddressRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private UuidGeneratorInterface $uuidGenerator,
        private AddressMapper $mapper,
    ) {
    }

    public function nextIdentity(): AddressId
    {
        return AddressId::fromString($this->uuidGenerator->generate());
    }

    public function listByOwner(CustomerId $ownerId, Pagination $pagination, array $orderBy, array $filters): AddressList
    {
        $qb = $this->createQueryBuilder()
            ->andWhere('a.customer = :customer')
            ->setParameter('customer', $this->getCustomerReference($ownerId));

        $this->applyFilters($qb, $filters);
        $this->applyOrdering($qb, $orderBy);

        $offset = max(0, ($pagination->page - 1) * $pagination->itemsPerPage);
        $qb->setFirstResult($offset)->setMaxResults($pagination->itemsPerPage);

        $paginator = new Paginator($qb);
        $totalItems = count($paginator);
        $totalPages = $pagination->itemsPerPage > 0 ? (int) ceil($totalItems / $pagination->itemsPerPage) : 1;

        $addresses = [];
        foreach ($paginator as $entity) {
            if ($entity instanceof DoctrineAddress) {
                $addresses[] = $this->mapper->toDomain($entity);
            }
        }

        return new AddressList(
            addresses: $addresses,
            totalItems: $totalItems,
            totalPages: $totalPages,
        );
    }

    public function save(DomainAddress $address): void
    {
        $entity = $this->findEntity($address->getId());
        $entity = $this->mapper->toDoctrine($address, $entity);
        $entity->setCustomer($this->getCustomerReference($address->getOwnerId()));

        $this->em->persist($entity);
        $this->em->flush();
    }

    public function delete(DomainAddress $address): void
    {
        $entity = $this->findEntity($address->getId());
        if (null === $entity) {
            return;
        }

        $this->em->remove($entity);
        $this->em->flush();
    }

    public function findById(AddressId $id): ?DomainAddress
    {
        $entity = $this->findEntity($id);

        return null === $entity ? null : $this->mapper->toDomain($entity);
    }

    private function findEntity(AddressId $id): ?DoctrineAddress
    {
        $entity = $this->em->find(DoctrineAddress::class, $id->toString());

        return $entity instanceof DoctrineAddress ? $entity : null;
    }

    private function getCustomerReference(CustomerId $ownerId): DoctrineCustomer
    {
        return $this->em->getReference(DoctrineCustomer::class, $ownerId->toString());
    }

    private function createQueryBuilder(): QueryBuilder
    {
        return $this->em->createQueryBuilder()
            ->select('a')
            ->from(DoctrineAddress::class, 'a');
    }

    private function applyFilters(QueryBuilder $qb, array $filters): void
    {
        $name = $filters['name'] ?? null;
        if (is_string($name) && '' !== trim($name)) {
            $qb->andWhere('a.name LIKE :name')
                ->setParameter('name', '%' . trim($name) . '%');
        }

        $city = $filters['city'] ?? null;
        if (is_string($city) && '' !== trim($city)) {
            $qb->andWhere('a.city LIKE :city')
                ->setParameter('city', '%' . trim($city) . '%');
        }

        $country = $filters['country'] ?? null;
        if (is_string($country) && '' !== trim($country)) {
            $qb->andWhere('a.country = :country')
                ->setParameter('country', trim($country));
        }
    }

    private function applyOrdering(QueryBuilder $qb, array $orderBy): void
    {
        $allowedFields = [
            'name' => 'a.name',
            'city' => 'a.city',
            'country' => 'a.country',
            'createdAt' => 'a.createdAt',
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
