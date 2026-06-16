<?php

declare(strict_types=1);

namespace App\Presentation\Tests\Unit\State\Shop\Ordering\Cart;

use ApiPlatform\Metadata\Operation;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\Shop\ReadModel\Ordering\CartItem;
use App\Application\Shop\UseCase\Command\Ordering\UpdateCartLine\UpdateCartLineCommand;
use App\Application\Shop\UseCase\Command\Ordering\UpdateCartLine\UpdateCartLineOutput;
use App\Application\Shop\UseCase\Query\Customer\DisplayMyCustomer\DisplayMyCustomerOutput;
use App\Application\Shop\UseCase\Query\Customer\DisplayMyCustomer\DisplayMyCustomerQuery;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Customer\ValueObject\CustomerStatus;
use App\Domain\Shop\Customer\ValueObject\UserAccountId;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\Shop\ApiResource\Ordering\CartResource;
use App\Presentation\Shop\Dto\Ordering\Cart\CartLinePatchInput;
use App\Presentation\Shop\Presenter\Ordering\CartResourcePresenter;
use App\Presentation\Shop\State\Ordering\Cart\CartLinePatchProcessor;
use App\Presentation\Shop\State\Shared\CurrentCustomerResolver;
use App\Presentation\Tests\Unit\State\Shop\Customer\CustomerUserTrait;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Bundle\SecurityBundle\Security;

final class CartLinePatchProcessorTest extends TestCase
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

    public function testProcessUpdatesCartLineQuantity(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects($this->once())
            ->method('getUser')
            ->willReturn($this->createUser('550e8400-e29b-41d4-a716-446655440900'));

        $processor = new CartLinePatchProcessor(
            $this->commandBus,
            new CurrentCustomerResolver($this->queryBus, $security),
            new CartResourcePresenter(),
        );

        $input = new CartLinePatchInput();
        $input->quantity = 5;

        $customerId = CustomerId::fromString('550e8400-e29b-41d4-a716-446655440901');
        $customerOutput = new DisplayMyCustomerOutput($customerId, CustomerStatus::active());
        $output = new UpdateCartLineOutput($this->createCart());

        $this->queryBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($query) use ($customerOutput): DisplayMyCustomerOutput {
                $this->assertInstanceOf(DisplayMyCustomerQuery::class, $query);
                $this->assertTrue($query->userAccountId->equals(UserAccountId::fromString('550e8400-e29b-41d4-a716-446655440900')));

                return $customerOutput;
            });

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($command) use ($customerId, $output): UpdateCartLineOutput {
                $this->assertInstanceOf(UpdateCartLineCommand::class, $command);
                $this->assertTrue($command->customerId->equals($customerId));
                $this->assertSame('550e8400-e29b-41d4-a716-446655440912', $command->productId->toString());
                $this->assertSame(5, $command->quantity);

                return $output;
            });

        $result = $processor->process(
            $input,
            $this->operation,
            ['productId' => '550e8400-e29b-41d4-a716-446655440912'],
        );

        $this->assertInstanceOf(CartResource::class, $result);
        $this->assertSame('550e8400-e29b-41d4-a716-446655440910', $result->id);
    }

    public function testProcessThrowsLogicExceptionForInvalidInput(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects($this->never())->method('getUser');

        $processor = new CartLinePatchProcessor(
            $this->commandBus,
            new CurrentCustomerResolver($this->queryBus, $security),
            new CartResourcePresenter(),
        );

        $this->commandBus->expects($this->never())->method('dispatch');
        $this->queryBus->expects($this->never())->method('dispatch');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $processor->process(new stdClass(), $this->operation, ['productId' => '550e8400-e29b-41d4-a716-446655440912']);
    }

    public function testProcessThrowsLogicExceptionForMissingProductId(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects($this->never())->method('getUser');

        $processor = new CartLinePatchProcessor(
            $this->commandBus,
            new CurrentCustomerResolver($this->queryBus, $security),
            new CartResourcePresenter(),
        );

        $input = new CartLinePatchInput();
        $input->quantity = 5;

        $this->commandBus->expects($this->never())->method('dispatch');
        $this->queryBus->expects($this->never())->method('dispatch');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $processor->process($input, $this->operation, []);
    }

    public function testProcessThrowsLogicExceptionForEmptyProductId(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects($this->never())->method('getUser');

        $processor = new CartLinePatchProcessor(
            $this->commandBus,
            new CurrentCustomerResolver($this->queryBus, $security),
            new CartResourcePresenter(),
        );

        $input = new CartLinePatchInput();
        $input->quantity = 5;

        $this->commandBus->expects($this->never())->method('dispatch');
        $this->queryBus->expects($this->never())->method('dispatch');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $processor->process($input, $this->operation, ['productId' => '']);
    }

    private function createCart(): CartItem
    {
        return new CartItem(
            id: '550e8400-e29b-41d4-a716-446655440910',
            items: [],
            totalQuantity: 5,
            subtotal: 99.95,
            currency: 'EUR',
            createdAt: null,
            updatedAt: null,
        );
    }
}
