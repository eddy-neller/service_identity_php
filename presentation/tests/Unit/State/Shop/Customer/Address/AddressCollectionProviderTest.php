<?php

declare(strict_types=1);

namespace App\Presentation\Tests\Unit\State\Shop\Customer\Address;

use ApiPlatform\Metadata\GetCollection;
use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\Shop\ReadModel\Customer\AddressItem;
use App\Application\Shop\ReadModel\Customer\AddressList;
use App\Application\Shop\ReadModel\Customer\CurrentCustomerItem;
use App\Application\Shop\UseCase\Query\Customer\DisplayListAddress\DisplayListAddressQuery;
use App\Application\Shop\UseCase\Query\Customer\DisplayMyCustomer\DisplayMyCustomerQuery;
use App\Domain\Shop\Customer\Model\Address as DomainAddress;
use App\Domain\Shop\Customer\ValueObject\AddressId;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Presentation\Shop\ApiResource\Customer\AddressResource;
use App\Presentation\Shop\Presenter\Customer\AddressResourcePresenter;
use App\Presentation\Shop\State\Customer\Address\AddressCollectionProvider;
use App\Presentation\Shop\State\Shared\CurrentCustomerResolver;
use App\Presentation\Tests\Unit\State\Shop\Customer\CustomerUserTrait;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;

final class AddressCollectionProviderTest extends TestCase
{
    use CustomerUserTrait;

    public function testItMapsAddressesAndSetsPagination(): void
    {
        $request = new Request();
        $queryBus = $this->createMock(QueryBusInterface::class);
        $security = $this->createMock(Security::class);

        $user = $this->createUser('550e8400-e29b-41d4-a716-446655440200');
        $security->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $customerId = CustomerId::fromString('550e8400-e29b-41d4-a716-446655440201');
        $customerOutput = new CurrentCustomerItem($customerId->toString());
        $address = $this->createAddress($customerId);
        $listOutput = new AddressList([AddressItem::fromAddress($address)], 2, 1);

        $queryBus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($query) use ($customerOutput, $listOutput): CurrentCustomerItem|AddressList {
                if ($query instanceof DisplayMyCustomerQuery) {
                    $this->assertSame('550e8400-e29b-41d4-a716-446655440200', $query->userAccountId);

                    return $customerOutput;
                }

                if ($query instanceof DisplayListAddressQuery) {
                    $this->assertSame('550e8400-e29b-41d4-a716-446655440201', $query->ownerId);
                    $this->assertSame('2', $query->page);
                    $this->assertSame('15', $query->itemsPerPage);
                    $this->assertSame(['name' => 'ASC'], $query->orderBy);
                    $this->assertSame([
                        'page' => '2',
                        'itemsPerPage' => '15',
                        'order' => [
                            'name' => 'asc',
                        ],
                        'name' => 'Office',
                    ], $query->filters);

                    return $listOutput;
                }

                $this->fail('Unexpected query dispatched.');
            });

        $provider = new AddressCollectionProvider($queryBus, new CurrentCustomerResolver($queryBus, $security), new AddressResourcePresenter());

        $result = $provider->provide(
            new GetCollection(name: 'shop-addresses-me-col'),
            context: [
                'request' => $request,
                'filters' => [
                    'page' => '2',
                    'itemsPerPage' => '15',
                    'order' => [
                        'name' => 'asc',
                    ],
                    'name' => 'Office',
                ],
            ],
        );

        $this->assertCount(1, $result);
        $this->assertInstanceOf(AddressResource::class, $result[0]);
        $this->assertSame('Office', $result[0]->name);
        $this->assertSame(2, $request->attributes->get('_total_items'));
        $this->assertSame(1, $request->attributes->get('_total_pages'));
    }

    public function testItHandlesInvalidFiltersWithoutRequest(): void
    {
        $queryBus = $this->createMock(QueryBusInterface::class);
        $security = $this->createMock(Security::class);

        $user = $this->createUser('550e8400-e29b-41d4-a716-446655440210');
        $security->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $customerId = CustomerId::fromString('550e8400-e29b-41d4-a716-446655440211');
        $customerOutput = new CurrentCustomerItem($customerId->toString());
        $address = $this->createAddress($customerId);
        $listOutput = new AddressList([AddressItem::fromAddress($address)], 1, 1);

        $queryBus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnOnConsecutiveCalls($customerOutput, $listOutput);

        $provider = new AddressCollectionProvider($queryBus, new CurrentCustomerResolver($queryBus, $security), new AddressResourcePresenter());

        $result = $provider->provide(
            new GetCollection(name: 'shop-addresses-me-col'),
            context: [
                'filters' => 'not-an-array',
            ],
        );

        $this->assertCount(1, $result);
        $this->assertInstanceOf(AddressResource::class, $result[0]);
    }

    private function createAddress(CustomerId $customerId): DomainAddress
    {
        return DomainAddress::reconstitute(
            id: AddressId::fromString('550e8400-e29b-41d4-a716-446655440202'),
            ownerId: $customerId,
            label: 'Office',
            firstname: 'John',
            lastname: 'Doe',
            street: '12 Main St',
            zipCode: '12345',
            city: 'Paris',
            country: 'France',
            phone: '+33 1 23 45 67 89',
            createdAt: new DateTimeImmutable('2025-01-01 10:00:00'),
            updatedAt: new DateTimeImmutable('2025-01-02 10:00:00'),
            company: 'ACME',
        );
    }
}
