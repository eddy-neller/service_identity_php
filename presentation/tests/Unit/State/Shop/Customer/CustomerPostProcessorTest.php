<?php

declare(strict_types=1);

namespace App\Presentation\Tests\Unit\State\Shop\Customer;

use ApiPlatform\Metadata\Operation;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\Shop\UseCase\Command\Customer\CreateCustomer\CreateCustomerCommand;
use App\Application\Shop\UseCase\Command\Customer\CreateCustomer\CreateCustomerOutput;
use App\Domain\Shop\Customer\Model\Customer;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Customer\ValueObject\CustomerStatus;
use App\Domain\Shop\Customer\ValueObject\UserAccountId;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\Shop\ApiResource\Customer\CustomerResource;
use App\Presentation\Shop\Dto\Customer\CustomerPostInput;
use App\Presentation\Shop\Presenter\Customer\AddressResourcePresenter;
use App\Presentation\Shop\Presenter\Customer\CustomerResourcePresenter;
use App\Presentation\Shop\State\Customer\Customer\CustomerPostProcessor;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;

final class CustomerPostProcessorTest extends TestCase
{
    private CommandBusInterface&MockObject $commandBus;

    private Operation&MockObject $operation;

    private CustomerPostProcessor $processor;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(CommandBusInterface::class);
        $this->operation = $this->createMock(Operation::class);
        $this->operation->expects($this->never())
            ->method('getName');

        $this->processor = new CustomerPostProcessor(
            $this->commandBus,
            new CustomerResourcePresenter(new AddressResourcePresenter()),
        );
    }

    public function testProcessWithValidInputDispatchesCommand(): void
    {
        $input = new CustomerPostInput();
        $input->userAccountId = '550e8400-e29b-41d4-a716-446655440700';

        $expectedUserAccountId = UserAccountId::fromString($input->userAccountId);

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($command) use ($expectedUserAccountId): CreateCustomerOutput {
                $this->assertInstanceOf(CreateCustomerCommand::class, $command);
                $this->assertTrue($command->userAccountId->equals($expectedUserAccountId));

                return new CreateCustomerOutput(
                    Customer::reconstitute(
                        id: CustomerId::fromString('550e8400-e29b-41d4-a716-446655440701'),
                        status: CustomerStatus::active(),
                        createdAt: new DateTimeImmutable('2025-01-01 10:00:00'),
                        updatedAt: new DateTimeImmutable('2025-01-01 10:00:00'),
                        userAccountId: $expectedUserAccountId,
                    ),
                );
            });

        $result = $this->processor->process($input, $this->operation);

        $this->assertInstanceOf(CustomerResource::class, $result);
        $this->assertSame('550e8400-e29b-41d4-a716-446655440701', $result->id);
        $this->assertSame($input->userAccountId, $result->userAccountId);
    }

    public function testProcessThrowsLogicExceptionForInvalidInput(): void
    {
        $this->commandBus->expects($this->never())
            ->method('dispatch');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->processor->process(new stdClass(), $this->operation);
    }
}
