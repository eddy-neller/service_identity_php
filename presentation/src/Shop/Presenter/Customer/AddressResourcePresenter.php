<?php

declare(strict_types=1);

namespace App\Presentation\Shop\Presenter\Customer;

use App\Domain\Shop\Customer\Model\Address as DomainAddress;
use App\Presentation\Shop\ApiResource\Customer\AddressResource;

final readonly class AddressResourcePresenter
{
    public function toResource(DomainAddress $address): AddressResource
    {
        $resource = new AddressResource();

        $resource->id = $address->getId()->toString();
        $resource->name = $address->getLabel();
        $resource->firstname = $address->getFirstname();
        $resource->lastname = $address->getLastname();
        $resource->company = $address->getCompany();
        $resource->address = $address->getStreet();
        $resource->zip = $address->getZipCode();
        $resource->city = $address->getCity();
        $resource->country = $address->getCountry();
        $resource->phone = $address->getPhone();
        $resource->isDefault = $address->isDefault();
        $resource->createdAt = $address->getCreatedAt();
        $resource->updatedAt = $address->getUpdatedAt();

        return $resource;
    }
}
