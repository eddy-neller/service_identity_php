<?php

declare(strict_types=1);

namespace App\Presentation\Tests\Unit\State\Shop\Ordering\Cart;

use ApiPlatform\Metadata\Get;
use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\Shop\ReadModel\Ordering\CartItem;
use App\Application\Shop\ReadModel\Ordering\CartLineItem;
use App\Application\Shop\UseCase\Query\Customer\DisplayMyCustomer\DisplayMyCustomerOutput;
use App\Application\Shop\UseCase\Query\Customer\DisplayMyCustomer\DisplayMyCustomerQuery;
use App\Application\Shop\UseCase\Query\Ordering\DisplayMyCart\DisplayMyCartOutput;
use App\Application\Shop\UseCase\Query\Ordering\DisplayMyCart\DisplayMyCartQuery;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Customer\ValueObject\CustomerStatus;
use App\Domain\Shop\Customer\ValueObject\UserAccountId;
use App\Presentation\Shop\ApiResource\Ordering\CartResource;
use App\Presentation\Shop\Presenter\Ordering\CartResourcePresenter;
use App\Presentation\Shop\State\Ordering\Cart\CartGetProvider;
use App\Presentation\Shop\State\Shared\CurrentCustomerResolver;
use App\Presentation\Tests\Unit\State\Shop\Customer\CustomerUserTrait;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class CartGetProviderTest extends TestCase
{
    use CustomerUserTrait;

    public function testItReturnsCartResource(): void
    {
        $queryBus = $this->createMock(QueryBusInterface::class);
        $security = $this->createMock(Security::class);

        $security->expects($this->once())
            ->method('getUser')
            ->willReturn($this->createUser('550e8400-e29b-41d4-a716-446655440700'));

        $customerId = CustomerId::fromString('550e8400-e29b-41d4-a716-446655440701');
        $customerOutput = new DisplayMyCustomerOutput($customerId, CustomerStatus::active());
        $cartOutput = new DisplayMyCartOutput($this->createCart());

        $queryBus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($query) use ($customerId, $customerOutput, $cartOutput): DisplayMyCustomerOutput|DisplayMyCartOutput {
                if ($query instanceof DisplayMyCustomerQuery) {
                    $this->assertTrue($query->userAccountId->equals(UserAccountId::fromString('550e8400-e29b-41d4-a716-446655440700')));

                    return $customerOutput;
                }

                if ($query instanceof DisplayMyCartQuery) {
                    $this->assertTrue($query->customerId->equals($customerId));

                    return $cartOutput;
                }

                $this->fail('Unexpected query dispatched.');
            });

        $provider = new CartGetProvider($queryBus, new CurrentCustomerResolver($queryBus, $security), new CartResourcePresenter());

        $result = $provider->provide(new Get(name: 'shop-cart-get'));

        $this->assertInstanceOf(CartResource::class, $result);
        $this->assertSame('550e8400-e29b-41d4-a716-446655440710', $result->id);
        $this->assertSame(3, $result->totalQuantity);
        $this->assertEqualsWithDelta(59.97, $result->subtotal, PHP_FLOAT_EPSILON);
        $this->assertSame('EUR', $result->currency);
        $this->assertCount(1, $result->items);
        $this->assertSame('Mug', $result->items[0]->productTitle);
        $this->assertSame(3, $result->items[0]->quantity);
    }

    private function createCart(): CartItem
    {
        return new CartItem(
            id: '550e8400-e29b-41d4-a716-446655440710',
            items: [
                new CartLineItem(
                    id: '550e8400-e29b-41d4-a716-446655440711',
                    productId: '550e8400-e29b-41d4-a716-446655440712',
                    productTitle: 'Mug',
                    productSlug: 'mug',
                    imageUrl: 'https://example.com/mug.png',
                    unitPrice: 19.99,
                    quantity: 3,
                    lineTotal: 59.97,
                ),
            ],
            totalQuantity: 3,
            subtotal: 59.97,
            currency: 'EUR',
            createdAt: new DateTimeImmutable('2025-01-01 10:00:00'),
            updatedAt: new DateTimeImmutable('2025-01-02 10:00:00'),
        );
    }
}
