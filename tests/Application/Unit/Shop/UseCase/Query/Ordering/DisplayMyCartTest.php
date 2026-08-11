<?php

declare(strict_types=1);

namespace App\Tests\Application\Unit\Shop\UseCase\Query\Ordering;

use App\Application\Shop\Port\CartRepositoryInterface;
use App\Application\Shop\Port\ProductRepositoryInterface;
use App\Application\Shop\ReadModel\Ordering\CartItem;
use App\Application\Shop\Service\CartItemFactory;
use App\Application\Shop\UseCase\Query\Ordering\DisplayMyCart\DisplayMyCartQuery;
use App\Application\Shop\UseCase\Query\Ordering\DisplayMyCart\DisplayMyCartQueryHandler;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Ordering\Model\Cart;
use App\Domain\Shop\Ordering\ValueObject\CartId;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DisplayMyCartTest extends TestCase
{
    private const string CART_ID = '550e8400-e29b-41d4-a716-446655440140';

    private const string CUSTOMER_ID = '550e8400-e29b-41d4-a716-446655440142';

    private CartRepositoryInterface&MockObject $repository;

    private ProductRepositoryInterface&MockObject $productRepository;

    private DisplayMyCartQueryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(CartRepositoryInterface::class);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->handler = new DisplayMyCartQueryHandler(
            $this->repository,
            new CartItemFactory($this->productRepository),
        );
    }

    public function testHandleReturnsCartWhenItExists(): void
    {
        $customerId = CustomerId::fromString(self::CUSTOMER_ID);
        $cart = Cart::create(CartId::fromString(self::CART_ID), $customerId, new DateTimeImmutable('2025-01-01 09:00:00'));
        $query = new DisplayMyCartQuery($customerId->toString());

        $this->repository->expects($this->once())
            ->method('findByOwner')
            ->with($customerId)
            ->willReturn($cart);

        $this->productRepository->expects($this->once())->method('findByIds')->willReturn([]);

        $output = $this->handler->handle($query);

        $this->assertInstanceOf(CartItem::class, $output);
        $this->assertSame(self::CART_ID, $output->id);
    }

    public function testHandleReturnsEmptyCartWhenNoneExists(): void
    {
        $customerId = CustomerId::fromString(self::CUSTOMER_ID);
        $query = new DisplayMyCartQuery($customerId->toString());

        $this->repository->expects($this->once())
            ->method('findByOwner')
            ->with($customerId)
            ->willReturn(null);

        $this->productRepository->expects($this->never())->method('findByIds');

        $output = $this->handler->handle($query);

        $this->assertInstanceOf(CartItem::class, $output);
        $this->assertNull($output->id);
        $this->assertSame([], $output->items);
        $this->assertSame(0, $output->totalQuantity);
    }
}
