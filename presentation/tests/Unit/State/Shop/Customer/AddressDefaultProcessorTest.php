<?php

declare(strict_types=1);

namespace App\Presentation\Tests\Unit\State\Shop\Customer;

use ApiPlatform\Metadata\Operation;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\Shop\ReadModel\AddressItem;
use App\Application\Shop\UseCase\Command\Customer\SetDefaultAddress\SetDefaultAddressCommand;
use App\Application\Shop\UseCase\Command\Customer\SetDefaultAddress\SetDefaultAddressOutput;
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
use App\Presentation\Shop\State\Customer\Address\AddressDefaultProcessor;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class AddressDefaultProcessorTest extends TestCase
{
    use CustomerUserTrait;

    private CommandBusInterface&MockObject $commandBus;

    private QueryBusInterface&MockObject $queryBus;

    private Operation&MockObject $operation;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(CommandBusInterface::class);
        $this->queryBus = $this->createMock(QueryBusInterface::class);
        $this->operation = $this->createMock(Operation::class);
        $this->operation->expects($this->never())->method('getName');
    }

    public function testProcessSetsDefaultAddress(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects($this->once())
            ->method('getUser')
            ->willReturn($this->createUser('550e8400-e29b-41d4-a716-446655440600'));

        $processor = new AddressDefaultProcessor(
            $this->commandBus,
            $this->queryBus,
            new AddressResourcePresenter(),
            $security,
        );

        $customerId = CustomerId::fromString('550e8400-e29b-41d4-a716-446655440601');
        $addressId = AddressId::fromString('550e8400-e29b-41d4-a716-446655440602');
        $customerOutput = new DisplayMyCustomerOutput($customerId, CustomerStatus::active());
        $address = DomainAddress::create(
            id: $addressId,
            ownerId: $customerId,
            label: 'Office',
            firstname: 'John',
            lastname: 'Doe',
            street: '12 Main St',
            zipCode: '12345',
            city: 'Paris',
            country: 'France',
            phone: '+33 1 23 45 67 89',
            now: new DateTimeImmutable('2025-01-01 10:00:00'),
            isDefault: true,
        );
        $output = new SetDefaultAddressOutput(new AddressItem($address));

        $this->queryBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($query) use ($customerOutput): DisplayMyCustomerOutput {
                $this->assertInstanceOf(DisplayMyCustomerQuery::class, $query);
                $this->assertTrue($query->userAccountId->equals(UserAccountId::fromString('550e8400-e29b-41d4-a716-446655440600')));

                return $customerOutput;
            });

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($command) use ($customerId, $addressId, $output): SetDefaultAddressOutput {
                $this->assertInstanceOf(SetDefaultAddressCommand::class, $command);
                $this->assertTrue($command->ownerId->equals($customerId));
                $this->assertTrue($command->addressId->equals($addressId));

                return $output;
            });

        $result = $processor->process(null, $this->operation, ['id' => $addressId->toString()]);

        $this->assertInstanceOf(AddressResource::class, $result);
        $this->assertTrue($result->isDefault);
    }

    public function testProcessThrowsOnInvalidIdType(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects($this->never())
            ->method('getUser');

        $processor = new AddressDefaultProcessor(
            $this->commandBus,
            $this->queryBus,
            new AddressResourcePresenter(),
            $security,
        );

        $this->commandBus->expects($this->never())->method('dispatch');
        $this->queryBus->expects($this->never())->method('dispatch');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $processor->process(null, $this->operation, ['id' => 123]);
    }
}
