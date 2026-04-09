<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Shop\Customer;

use App\Domain\Shop\Customer\Model\Customer as DomainCustomer;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Customer\ValueObject\CustomerStatus;
use App\Domain\Shop\Customer\ValueObject\UserAccountId;
use App\Infrastructure\Entity\Shop\Customer as DoctrineCustomer;
use Ramsey\Uuid\Uuid;

final readonly class CustomerMapper
{
    public function toDomain(DoctrineCustomer $entity): DomainCustomer
    {
        return DomainCustomer::reconstitute(
            id: CustomerId::fromString($entity->getId()->toString()),
            status: CustomerStatus::fromInt($entity->getStatus()),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
            userAccountId: UserAccountId::fromString($entity->getUserAccountId()->toString()),
        );
    }

    public function toDoctrine(DomainCustomer $customer, ?DoctrineCustomer $entity = null): DoctrineCustomer
    {
        $entity ??= new DoctrineCustomer();

        $entity->setId(Uuid::fromString($customer->getId()->toString()));
        $entity->setUserAccountId(Uuid::fromString($customer->getUserAccountId()->toString()));
        $entity->setStatus($customer->getStatus()->toInt());

        return $entity;
    }
}
