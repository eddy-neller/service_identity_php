<?php

declare(strict_types=1);

namespace App\Presentation\Tests\Unit\State\Shop\Customer;

use ApiPlatform\Metadata\GetCollection;
use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\Shop\UseCase\Query\Customer\DisplayListAddress\DisplayListAddressOutput;
use App\Application\Shop\UseCase\Query\Customer\DisplayListAddress\DisplayListAddressQuery;
use App\Application\Shop\UseCase\Query\Customer\DisplayMyCustomer\DisplayMyCustomerOutput;
use App\Application\Shop\UseCase\Query\Customer\DisplayMyCustomer\DisplayMyCustomerQuery;
use App\Domain\Shop\Customer\Model\Address as DomainAddress;
use App\Domain\Shop\Customer\ValueObject\AddressId;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Customer\ValueObject\CustomerStatus;
use App\Domain\Shop\Customer\ValueObject\UserAccountId;
use App\Presentation\Shop\ApiResource\Customer\AddressResource;
use App\Presentation\Shop\Presenter\Customer\AddressResourcePresenter;
use App\Presentation\Shop\State\Customer\Address\AddressCollectionProvider;
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
        $customerOutput = new DisplayMyCustomerOutput($customerId, CustomerStatus::active());
        $address = $this->createAddress($customerId);
        $listOutput = new DisplayListAddressOutput([$address], 2, 1);

        $queryBus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($query) use ($customerOutput, $listOutput): DisplayMyCustomerOutput|DisplayListAddressOutput {
                if ($query instanceof DisplayMyCustomerQuery) {
                    $this->assertTrue($query->userAccountId->equals(UserAccountId::fromString('550e8400-e29b-41d4-a716-446655440200')));

                    return $customerOutput;
                }

                if ($query instanceof DisplayListAddressQuery) {
                    $this->assertTrue($query->ownerId->equals(CustomerId::fromString('550e8400-e29b-41d4-a716-446655440201')));
                    $this->assertSame(2, $query->pagination->page);
                    $this->assertSame(15, $query->pagination->itemsPerPage);
                    $this->assertSame(['name' => 'asc'], $query->orderBy);
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

        $provider = new AddressCollectionProvider($queryBus, new AddressResourcePresenter(), $security);

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
        $customerOutput = new DisplayMyCustomerOutput($customerId, CustomerStatus::active());
        $address = $this->createAddress($customerId);
        $listOutput = new DisplayListAddressOutput([$address], 1, 1);

        $queryBus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnOnConsecutiveCalls($customerOutput, $listOutput);

        $provider = new AddressCollectionProvider($queryBus, new AddressResourcePresenter(), $security);

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
