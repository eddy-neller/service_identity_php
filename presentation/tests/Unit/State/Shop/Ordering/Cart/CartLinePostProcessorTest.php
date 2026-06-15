<?php

declare(strict_types=1);

namespace App\Presentation\Tests\Unit\State\Shop\Ordering\Cart;

use ApiPlatform\Metadata\Operation;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\Shop\ReadModel\Ordering\CartItem;
use App\Application\Shop\UseCase\Command\Ordering\AddToCart\AddToCartCommand;
use App\Application\Shop\UseCase\Command\Ordering\AddToCart\AddToCartOutput;
use App\Application\Shop\UseCase\Query\Customer\DisplayMyCustomer\DisplayMyCustomerOutput;
use App\Application\Shop\UseCase\Query\Customer\DisplayMyCustomer\DisplayMyCustomerQuery;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Customer\ValueObject\CustomerStatus;
use App\Domain\Shop\Customer\ValueObject\UserAccountId;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\Shop\ApiResource\Ordering\CartResource;
use App\Presentation\Shop\Dto\Ordering\Cart\CartLinePostInput;
use App\Presentation\Shop\Presenter\Ordering\CartResourcePresenter;
use App\Presentation\Shop\State\Ordering\Cart\CartLinePostProcessor;
use App\Presentation\Shop\State\Shared\CurrentCustomerResolver;
use App\Presentation\Tests\Unit\State\Shop\Customer\CustomerUserTrait;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class CartLinePostProcessorTest extends TestCase
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

    public function testProcessAddsLineToCart(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects($this->once())
            ->method('getUser')
            ->willReturn($this->createUser('550e8400-e29b-41d4-a716-446655440800'));

        $processor = new CartLinePostProcessor(
            $this->commandBus,
            new CurrentCustomerResolver($this->queryBus, $security),
            new CartResourcePresenter(),
        );

        $input = new CartLinePostInput();
        $input->productId = '550e8400-e29b-41d4-a716-446655440812';
        $input->quantity = 2;

        $customerId = CustomerId::fromString('550e8400-e29b-41d4-a716-446655440801');
        $customerOutput = new DisplayMyCustomerOutput($customerId, CustomerStatus::active());
        $output = new AddToCartOutput($this->createCart());

        $this->queryBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($query) use ($customerOutput): DisplayMyCustomerOutput {
                $this->assertInstanceOf(DisplayMyCustomerQuery::class, $query);
                $this->assertTrue($query->userAccountId->equals(UserAccountId::fromString('550e8400-e29b-41d4-a716-446655440800')));

                return $customerOutput;
            });

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($command) use ($input, $customerId, $output): AddToCartOutput {
                $this->assertInstanceOf(AddToCartCommand::class, $command);
                $this->assertTrue($command->customerId->equals($customerId));
                $this->assertSame($input->productId, $command->productId->toString());
                $this->assertSame(2, $command->quantity);

                return $output;
            });

        $result = $processor->process($input, $this->operation);

        $this->assertInstanceOf(CartResource::class, $result);
        $this->assertSame('550e8400-e29b-41d4-a716-446655440810', $result->id);
    }

    public function testProcessThrowsLogicExceptionForInvalidInput(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects($this->never())->method('getUser');

        $processor = new CartLinePostProcessor(
            $this->commandBus,
            new CurrentCustomerResolver($this->queryBus, $security),
            new CartResourcePresenter(),
        );

        $this->commandBus->expects($this->never())->method('dispatch');
        $this->queryBus->expects($this->never())->method('dispatch');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $processor->process(new \stdClass(), $this->operation);
    }

    private function createCart(): CartItem
    {
        return new CartItem(
            id: '550e8400-e29b-41d4-a716-446655440810',
            items: [],
            totalQuantity: 2,
            subtotal: 39.98,
            currency: 'EUR',
            createdAt: null,
            updatedAt: null,
        );
    }
}
