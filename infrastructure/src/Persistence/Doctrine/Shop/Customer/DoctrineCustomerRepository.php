<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Shop\Customer;

use App\Application\Shared\Port\UuidGeneratorInterface;
use App\Application\Shop\Port\CustomerRepositoryInterface;
use App\Domain\Shop\Customer\Model\Customer as DomainCustomer;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Customer\ValueObject\UserAccountId;
use App\Infrastructure\Entity\Shop\Customer as DoctrineCustomer;
use Doctrine\ORM\EntityManagerInterface;
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

    public function save(DomainCustomer $customer): void
    {
        $entity = $this->findEntity($customer->getId());
        $entity = $this->mapper->toDoctrine($customer, $entity);

        $this->em->persist($entity);
        $this->em->flush();
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
}
