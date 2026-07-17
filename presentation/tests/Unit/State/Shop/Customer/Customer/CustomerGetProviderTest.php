<?php

declare(strict_types=1);

namespace App\Presentation\Tests\Unit\State\Shop\Customer\Customer;

use ApiPlatform\Metadata\Get;
use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\Shop\ReadModel\Customer\AddressItem;
use App\Application\Shop\ReadModel\Customer\CustomerItem;
use App\Application\Shop\UseCase\Query\Customer\DisplayCustomer\DisplayCustomerQuery;
use App\Domain\Shop\Customer\Model\Address as DomainAddress;
use App\Domain\Shop\Customer\Model\Customer;
use App\Domain\Shop\Customer\ValueObject\AddressId;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Customer\ValueObject\UserAccountId;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\Shop\ApiResource\Customer\CustomerResource;
use App\Presentation\Shop\Presenter\Customer\AddressResourcePresenter;
use App\Presentation\Shop\Presenter\Customer\CustomerResourcePresenter;
use App\Presentation\Shop\State\Customer\Customer\CustomerGetProvider;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;

final class CustomerGetProviderTest extends TestCase
{
    public function testProvideWithValidId(): void
    {
        $queryBus = $this->createMock(QueryBusInterface::class);
        $provider = new CustomerGetProvider(
            $queryBus,
            new CustomerResourcePresenter(new AddressResourcePresenter()),
        );

        $customerId = '550e8400-e29b-41d4-a716-446655440800';
        $customer = Customer::create(
            id: CustomerId::fromString($customerId),
            now: new DateTimeImmutable('2025-01-01 10:00:00'),
            userAccountId: UserAccountId::fromString('550e8400-e29b-41d4-a716-446655440801'),
        );
        $address = $this->createAddress(CustomerId::fromString($customerId));

        $queryBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($query) use ($customerId, $customer, $address): CustomerItem {
                $this->assertInstanceOf(DisplayCustomerQuery::class, $query);
                $this->assertSame($customerId, $query->customerId);

                return CustomerItem::fromCustomer($customer, [AddressItem::fromAddress($address)]);
            });

        $result = $provider->provide(
            new Get(name: 'shop-customers-get'),
            ['id' => $customerId],
        );

        $this->assertInstanceOf(CustomerResource::class, $result);
        $this->assertSame($customerId, $result->id);
        $this->assertSame('550e8400-e29b-41d4-a716-446655440801', $result->userAccountId);
        $this->assertSame(1, $result->nbAddress);
        $this->assertCount(1, $result->addresses);
        $this->assertSame('550e8400-e29b-41d4-a716-446655440802', $result->addresses[0]->id);
        $this->assertSame('Office', $result->addresses[0]->name);
    }

    public function testProvideThrowsLogicExceptionWhenIdMissing(): void
    {
        $queryBus = $this->createMock(QueryBusInterface::class);
        $queryBus->expects($this->never())->method('dispatch');

        $provider = new CustomerGetProvider(
            $queryBus,
            new CustomerResourcePresenter(new AddressResourcePresenter()),
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $provider->provide(new Get(name: 'shop-customers-get'), []);
    }

    public function testProvideThrowsLogicExceptionWhenIdIsNotString(): void
    {
        $queryBus = $this->createMock(QueryBusInterface::class);
        $queryBus->expects($this->never())->method('dispatch');

        $provider = new CustomerGetProvider(
            $queryBus,
            new CustomerResourcePresenter(new AddressResourcePresenter()),
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $provider->provide(new Get(name: 'shop-customers-get'), ['id' => 123]);
    }

    private function createAddress(CustomerId $customerId): DomainAddress
    {
        return DomainAddress::create(
            id: AddressId::fromString('550e8400-e29b-41d4-a716-446655440802'),
            ownerId: $customerId,
            label: 'Office',
            firstname: 'John',
            lastname: 'Doe',
            street: '12 Main St',
            zipCode: '12345',
            city: 'Paris',
            country: 'France',
            phone: '+33 1 23 45 67 89',
            now: new DateTimeImmutable('2025-01-02 10:00:00'),
        );
    }
}
