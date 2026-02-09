<?php

declare(strict_types=1);

namespace App\Presentation\Shop\Presenter\Customer;

use App\Application\Shop\ReadModel\CustomerItem;
use App\Domain\Shop\Customer\Model\Customer as DomainCustomer;
use App\Presentation\Shop\ApiResource\Customer\CustomerResource;

final readonly class CustomerResourcePresenter
{
    public function __construct(
        private AddressResourcePresenter $addressResourcePresenter,
    ) {
    }

    public function toResource(CustomerItem $customerItem): CustomerResource
    {
        $resource = $this->mapCustomer($customerItem->customer);

        $resource->nbAddress = count($customerItem->addresses);

        foreach ($customerItem->addresses as $address) {
            $resource->addresses[] = $this->addressResourcePresenter->toResource($address);
        }

        return $resource;
    }

    public function toSummaryResource(DomainCustomer $customer): CustomerResource
    {
        return $this->mapCustomer($customer);
    }

    /**
     * Flat mapping to prevent addresses queries in list/get payloads.
     */
    private function mapCustomer(DomainCustomer $customer): CustomerResource
    {
        $resource = new CustomerResource();

        $resource->id = $customer->getId()->toString();
        $resource->userAccountId = $customer->getUserAccountId()?->toString() ?? '';
        $resource->status = $customer->getStatus()->toInt();
        $resource->createdAt = $customer->getCreatedAt();
        $resource->updatedAt = $customer->getUpdatedAt();

        return $resource;
    }
}
