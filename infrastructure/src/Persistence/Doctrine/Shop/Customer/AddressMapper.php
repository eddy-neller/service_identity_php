<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Shop\Customer;

use App\Domain\Shop\Customer\Model\Address as DomainAddress;
use App\Domain\Shop\Customer\ValueObject\AddressId;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Infrastructure\Entity\Shop\Address as DoctrineAddress;
use Ramsey\Uuid\Uuid;

final readonly class AddressMapper
{
    public function toDomain(DoctrineAddress $entity): DomainAddress
    {
        return DomainAddress::reconstitute(
            id: AddressId::fromString($entity->getId()->toString()),
            ownerId: CustomerId::fromString($entity->getCustomer()->getId()->toString()),
            label: $entity->getName(),
            firstname: $entity->getFirstname(),
            lastname: $entity->getLastname(),
            street: $entity->getAddress(),
            zipCode: $entity->getZip(),
            city: $entity->getCity(),
            country: $entity->getCountry(),
            phone: $entity->getPhone(),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
            company: $entity->getCompany(),
            isDefault: $entity->getIsDefault(),
        );
    }

    public function toDoctrine(DomainAddress $address, ?DoctrineAddress $entity = null): DoctrineAddress
    {
        $entity ??= new DoctrineAddress();

        $entity->setId(Uuid::fromString($address->getId()->toString()));
        $entity->setName($address->getLabel());
        $entity->setFirstname($address->getFirstname());
        $entity->setLastname($address->getLastname());
        $entity->setCompany($address->getCompany());
        $entity->setAddress($address->getStreet());
        $entity->setZip($address->getZipCode());
        $entity->setCity($address->getCity());
        $entity->setCountry($address->getCountry());
        $entity->setPhone($address->getPhone());
        $entity->setIsDefault($address->isDefault());

        return $entity;
    }
}
