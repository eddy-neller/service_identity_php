<?php

declare(strict_types=1);

namespace App\Presentation\Tests\Unit\State\Shop\Ordering\Cart;

use ApiPlatform\Metadata\Operation;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\Shop\ReadModel\Customer\CurrentCustomerItem;
use App\Application\Shop\UseCase\Command\Ordering\ClearCart\ClearCartCommand;
use App\Application\Shop\UseCase\Query\Customer\DisplayMyCustomer\DisplayMyCustomerQuery;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Presentation\Shop\State\Ordering\Cart\CartDeleteProcessor;
use App\Presentation\Shop\State\Shared\CurrentCustomerResolver;
use App\Presentation\Tests\Unit\State\Shop\Customer\CustomerUserTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class CartDeleteProcessorTest extends TestCase
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

    public function testProcessClearsCart(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects($this->once())
            ->method('getUser')
            ->willReturn($this->createUser('550e8400-e29b-41d4-a716-446655441100'));

        $processor = new CartDeleteProcessor(
            $this->commandBus,
            new CurrentCustomerResolver($this->queryBus, $security),
        );

        $customerId = CustomerId::fromString('550e8400-e29b-41d4-a716-446655441101');
        $customerOutput = new CurrentCustomerItem($customerId->toString());

        $this->queryBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($query) use ($customerOutput): CurrentCustomerItem {
                $this->assertInstanceOf(DisplayMyCustomerQuery::class, $query);
                $this->assertSame('550e8400-e29b-41d4-a716-446655441100', $query->userAccountId);

                return $customerOutput;
            });

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($command) use ($customerId): mixed {
                $this->assertInstanceOf(ClearCartCommand::class, $command);
                $this->assertSame($customerId->toString(), $command->customerId);

                return null;
            });

        $processor->process(null, $this->operation);
    }
}
