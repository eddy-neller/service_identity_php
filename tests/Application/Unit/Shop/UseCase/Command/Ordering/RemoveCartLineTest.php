<?php

declare(strict_types=1);

namespace App\Tests\Application\Unit\Shop\UseCase\Command\Ordering;

use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\CartRepositoryInterface;
use App\Application\Shop\UseCase\Command\Ordering\RemoveCartLine\RemoveCartLineCommand;
use App\Application\Shop\UseCase\Command\Ordering\RemoveCartLine\RemoveCartLineCommandHandler;
use App\Domain\Shop\Catalog\ValueObject\ProductId;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Ordering\Exception\CartLineNotFoundException;
use App\Domain\Shop\Ordering\Model\Cart;
use App\Domain\Shop\Ordering\Model\CartLine;
use App\Domain\Shop\Ordering\ValueObject\CartId;
use App\Domain\Shop\Ordering\ValueObject\CartLineId;
use App\Domain\Shop\Ordering\ValueObject\CartLineQuantity;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RemoveCartLineTest extends TestCase
{
    private const string CART_ID = '550e8400-e29b-41d4-a716-446655440120';

    private const string CART_LINE_ID = '550e8400-e29b-41d4-a716-446655440121';

    private const string CUSTOMER_ID = '550e8400-e29b-41d4-a716-446655440122';

    private const string PRODUCT_ID = '550e8400-e29b-41d4-a716-446655440123';

    private CartRepositoryInterface&MockObject $repository;

    private ClockInterface&MockObject $clock;

    private TransactionalInterface&MockObject $transactional;

    private RemoveCartLineCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(CartRepositoryInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->transactional = $this->createMock(TransactionalInterface::class);
        $this->handler = new RemoveCartLineCommandHandler(
            $this->repository,
            $this->clock,
            $this->transactional,
        );
    }

    public function testHandleThrowsWhenNoCartExists(): void
    {
        $customerId = CustomerId::fromString(self::CUSTOMER_ID);
        $command = new RemoveCartLineCommand($customerId->toString(), self::PRODUCT_ID);

        $this->repository->expects($this->once())
            ->method('findByOwnerForUpdate')
            ->with($customerId)
            ->willReturn(null);

        $this->clock->expects($this->never())->method('now');
        $this->repository->expects($this->never())->method('save');
        $this->expectTransaction();

        $this->expectException(CartLineNotFoundException::class);
        $this->expectExceptionMessage('Cart line not found.');

        $this->handler->handle($command);
    }

    public function testHandleRemovesLineFromCart(): void
    {
        $now = new DateTimeImmutable('2025-01-01 10:00:00');
        $customerId = CustomerId::fromString(self::CUSTOMER_ID);
        $productId = ProductId::fromString(self::PRODUCT_ID);
        $cart = Cart::reconstitute(
            CartId::fromString(self::CART_ID),
            $customerId,
            [CartLine::create(CartLineId::fromString(self::CART_LINE_ID), $productId, CartLineQuantity::fromInt(2))],
            new DateTimeImmutable('2025-01-01 09:00:00'),
            new DateTimeImmutable('2025-01-01 09:00:00'),
        );
        $command = new RemoveCartLineCommand($customerId->toString(), $productId->toString());

        $this->repository->expects($this->once())
            ->method('findByOwnerForUpdate')
            ->with($customerId)
            ->willReturn($cart);

        $this->clock->expects($this->atLeastOnce())->method('now')->willReturn($now);

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Cart $saved) use ($cart, $now): bool {
                return $saved === $cart
                    && [] === $saved->getLines()
                    && $saved->getUpdatedAt() === $now;
            }));

        $this->expectTransaction();

        $this->handler->handle($command);
    }

    private function expectTransaction(): void
    {
        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static fn (callable $callback) => $callback());
    }
}
