<?php

declare(strict_types=1);

namespace App\Presentation\Tests\Unit\State\Shop\Customer;

use ApiPlatform\Metadata\Operation;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\Shop\ReadModel\AddressItem;
use App\Application\Shop\UseCase\Command\Customer\CreateAddress\CreateAddressCommand;
use App\Application\Shop\UseCase\Command\Customer\CreateAddress\CreateAddressOutput;
use App\Application\Shop\UseCase\Query\Customer\DisplayMyCustomer\DisplayMyCustomerOutput;
use App\Application\Shop\UseCase\Query\Customer\DisplayMyCustomer\DisplayMyCustomerQuery;
use App\Domain\Shop\Customer\Model\Address as DomainAddress;
use App\Domain\Shop\Customer\ValueObject\AddressId;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Customer\ValueObject\CustomerStatus;
use App\Domain\Shop\Customer\ValueObject\UserAccountId;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\Shop\ApiResource\Customer\AddressResource;
use App\Presentation\Shop\Dto\Customer\Address\AddressPostInput;
use App\Presentation\Shop\Presenter\Customer\AddressResourcePresenter;
use App\Presentation\Shop\State\Customer\Address\AddressPostProcessor;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class AddressPostProcessorTest extends TestCase
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

    public function testProcessWithValidInput(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects($this->once())
            ->method('getUser')
            ->willReturn($this->createUser('550e8400-e29b-41d4-a716-446655440400'));

        $processor = new AddressPostProcessor(
            $this->commandBus,
            $this->queryBus,
            new AddressResourcePresenter(),
            $security,
        );

        $input = new AddressPostInput();
        $input->name = 'Office';
        $input->firstname = 'John';
        $input->lastname = 'Doe';
        $input->company = 'ACME';
        $input->address = '12 Main St';
        $input->zip = '12345';
        $input->city = 'Paris';
        $input->country = 'France';
        $input->phone = '+33 1 23 45 67 89';

        $customerId = CustomerId::fromString('550e8400-e29b-41d4-a716-446655440401');
        $customerOutput = new DisplayMyCustomerOutput($customerId, CustomerStatus::active());

        $address = $this->createAddress($customerId);
        $output = new CreateAddressOutput(new AddressItem($address));

        $this->queryBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($query) use ($customerOutput): DisplayMyCustomerOutput {
                $this->assertInstanceOf(DisplayMyCustomerQuery::class, $query);
                $this->assertTrue($query->userAccountId->equals(UserAccountId::fromString('550e8400-e29b-41d4-a716-446655440400')));

                return $customerOutput;
            });

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($command) use ($input, $customerId, $output): CreateAddressOutput {
                $this->assertInstanceOf(CreateAddressCommand::class, $command);
                $this->assertTrue($command->ownerId->equals($customerId));
                $this->assertSame($input->name, $command->label);
                $this->assertSame($input->firstname, $command->firstname);
                $this->assertSame($input->lastname, $command->lastname);
                $this->assertSame($input->company, $command->company);
                $this->assertSame($input->address, $command->street);
                $this->assertSame($input->zip, $command->zipCode);
                $this->assertSame($input->city, $command->city);
                $this->assertSame($input->country, $command->country);
                $this->assertSame($input->phone, $command->phone);

                return $output;
            });

        $result = $processor->process($input, $this->operation);

        $this->assertInstanceOf(AddressResource::class, $result);
        $this->assertSame('Office', $result->name);
    }

    public function testProcessThrowsLogicExceptionForInvalidInput(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects($this->never())
            ->method('getUser');

        $processor = new AddressPostProcessor(
            $this->commandBus,
            $this->queryBus,
            new AddressResourcePresenter(),
            $security,
        );

        $invalidInput = new \stdClass();

        $this->commandBus->expects($this->never())->method('dispatch');
        $this->queryBus->expects($this->never())->method('dispatch');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $processor->process($invalidInput, $this->operation);
    }

    private function createAddress(CustomerId $customerId): DomainAddress
    {
        return DomainAddress::reconstitute(
            id: AddressId::fromString('550e8400-e29b-41d4-a716-446655440402'),
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
