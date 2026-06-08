<?php

declare(strict_types=1);

namespace App\Presentation\Tests\Unit\Presenter\Shop\Customer;

use App\Application\Shop\ReadModel\Customer\CustomerItem;
use App\Domain\Shop\Customer\Model\Address as DomainAddress;
use App\Domain\Shop\Customer\Model\Customer;
use App\Domain\Shop\Customer\ValueObject\AddressId;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Customer\ValueObject\UserAccountId;
use App\Presentation\Shop\Presenter\Customer\AddressResourcePresenter;
use App\Presentation\Shop\Presenter\Customer\CustomerResourcePresenter;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CustomerResourcePresenterTest extends TestCase
{
    private CustomerResourcePresenter $presenter;

    protected function setUp(): void
    {
        $this->presenter = new CustomerResourcePresenter(new AddressResourcePresenter());
    }

    public function testToResourceMapsAllAddressesFromCustomerItem(): void
    {
        $now = new DateTimeImmutable('2025-01-01 10:00:00');
        $customerId = CustomerId::fromString('550e8400-e29b-41d4-a716-446655440900');
        $customer = Customer::create(
            id: $customerId,
            now: $now,
            userAccountId: UserAccountId::fromString('550e8400-e29b-41d4-a716-446655440901'),
        );

        $addressA = $this->createAddress('550e8400-e29b-41d4-a716-446655440902', $customerId, $now, 'Home');
        $addressB = $this->createAddress('550e8400-e29b-41d4-a716-446655440903', $customerId, $now, 'Office');

        $result = $this->presenter->toResource(new CustomerItem($customer, [$addressA, $addressB]));

        $this->assertSame('550e8400-e29b-41d4-a716-446655440900', $result->id);
        $this->assertSame(2, $result->nbAddress);
        $this->assertCount(2, $result->addresses);
        $this->assertSame('550e8400-e29b-41d4-a716-446655440902', $result->addresses[0]->id);
        $this->assertSame('Home', $result->addresses[0]->name);
        $this->assertSame('550e8400-e29b-41d4-a716-446655440903', $result->addresses[1]->id);
        $this->assertSame('Office', $result->addresses[1]->name);
    }

    private function createAddress(string $addressId, CustomerId $customerId, DateTimeImmutable $now, string $label): DomainAddress
    {
        return DomainAddress::create(
            id: AddressId::fromString($addressId),
            ownerId: $customerId,
            label: $label,
            firstname: 'John',
            lastname: 'Doe',
            street: '12 Main St',
            zipCode: '12345',
            city: 'Paris',
            country: 'France',
            phone: '+33 1 23 45 67 89',
            now: $now,
        );
    }
}
