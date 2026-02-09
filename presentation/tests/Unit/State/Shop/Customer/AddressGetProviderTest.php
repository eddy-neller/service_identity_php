<?php

declare(strict_types=1);

namespace App\Presentation\Tests\Unit\State\Shop\Customer;

use ApiPlatform\Metadata\Get;
use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\Shop\ReadModel\AddressItem;
use App\Application\Shop\UseCase\Query\Customer\DisplayAddress\DisplayAddressOutput;
use App\Application\Shop\UseCase\Query\Customer\DisplayAddress\DisplayAddressQuery;
use App\Application\Shop\UseCase\Query\Customer\DisplayMyCustomer\DisplayMyCustomerOutput;
use App\Application\Shop\UseCase\Query\Customer\DisplayMyCustomer\DisplayMyCustomerQuery;
use App\Domain\Shop\Customer\Model\Address as DomainAddress;
use App\Domain\Shop\Customer\ValueObject\AddressId;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Customer\ValueObject\CustomerStatus;
use App\Domain\Shop\Customer\ValueObject\UserAccountId;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\Shop\ApiResource\Customer\AddressResource;
use App\Presentation\Shop\Presenter\Customer\AddressResourcePresenter;
use App\Presentation\Shop\State\Customer\Address\AddressGetProvider;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class AddressGetProviderTest extends TestCase
{
    use CustomerUserTrait;
    public function testItReturnsAddressResource(): void
    {
        $queryBus = $this->createMock(QueryBusInterface::class);
        $security = $this->createMock(Security::class);

        $user = $this->createUser('550e8400-e29b-41d4-a716-446655440300');
        $security->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $customerId = CustomerId::fromString('550e8400-e29b-41d4-a716-446655440301');
        $customerOutput = new DisplayMyCustomerOutput($customerId, CustomerStatus::active());
        $address = $this->createAddress($customerId);
        $addressOutput = new DisplayAddressOutput(new AddressItem($address));

        $queryBus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($query) use ($customerOutput, $addressOutput): DisplayMyCustomerOutput|DisplayAddressOutput {
                if ($query instanceof DisplayMyCustomerQuery) {
                    $this->assertTrue($query->userAccountId->equals(UserAccountId::fromString('550e8400-e29b-41d4-a716-446655440300')));

                    return $customerOutput;
                }

                if ($query instanceof DisplayAddressQuery) {
                    $this->assertTrue($query->addressId->equals(AddressId::fromString('550e8400-e29b-41d4-a716-446655440302')));
                    $this->assertInstanceOf(CustomerId::class, $query->ownerId);

                    return $addressOutput;
                }

                $this->fail('Unexpected query dispatched.');
            });

        $provider = new AddressGetProvider($queryBus, new AddressResourcePresenter(), $security);

        $result = $provider->provide(
            new Get(name: 'shop-addresses-me-get'),
            ['id' => '550e8400-e29b-41d4-a716-446655440302'],
        );

        $this->assertInstanceOf(AddressResource::class, $result);
        $this->assertSame('Office', $result->name);
    }

    public function testItThrowsOnInvalidId(): void
    {
        $queryBus = $this->createStub(QueryBusInterface::class);
        $security = $this->createStub(Security::class);

        $provider = new AddressGetProvider($queryBus, new AddressResourcePresenter(), $security);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $provider->provide(new Get(name: 'shop-addresses-me-get'), ['id' => '']);
    }

    private function createAddress(CustomerId $customerId): DomainAddress
    {
        return DomainAddress::reconstitute(
            id: AddressId::fromString('550e8400-e29b-41d4-a716-446655440302'),
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
            company: null,
        );
    }
}
