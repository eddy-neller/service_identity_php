<?php

declare(strict_types=1);

namespace App\Presentation\Shop\ApiResource\Ordering;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use App\Presentation\RouteRequirements;
use App\Presentation\Shop\Dto\Ordering\Cart\CartLinePatchInput;
use App\Presentation\Shop\Dto\Ordering\Cart\CartLinePostInput;
use App\Presentation\Shop\State\Ordering\Cart\CartDeleteProcessor;
use App\Presentation\Shop\State\Ordering\Cart\CartGetProvider;
use App\Presentation\Shop\State\Ordering\Cart\CartLineDeleteProcessor;
use App\Presentation\Shop\State\Ordering\Cart\CartLinePatchProcessor;
use App\Presentation\Shop\State\Ordering\Cart\CartLinePostProcessor;
use DateTimeImmutable;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    shortName: 'ShopCart',
    operations: [
        new Get(
            uriTemplate: '/cart',
            openapi: new Model\Operation(security: [['JWT' => []]]),
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            name: self::PREFIX_NAME . 'get',
            provider: CartGetProvider::class,
        ),
        new Post(
            uriTemplate: '/cart/items',
            openapi: new Model\Operation(security: [['JWT' => []]]),
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            input: CartLinePostInput::class,
            name: self::PREFIX_NAME . 'item-post',
            processor: CartLinePostProcessor::class,
        ),
        new Patch(
            uriTemplate: '/cart/items/{productId}',
            uriVariables: ['productId' => new Link(fromClass: CartResource::class, identifiers: ['productId'])],
            requirements: ['productId' => RouteRequirements::UUID],
            openapi: new Model\Operation(security: [['JWT' => []]]),
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            input: CartLinePatchInput::class,
            read: false,
            name: self::PREFIX_NAME . 'item-patch',
            processor: CartLinePatchProcessor::class,
        ),
        new Delete(
            uriTemplate: '/cart/items/{productId}',
            uriVariables: ['productId' => new Link(fromClass: CartResource::class, identifiers: ['productId'])],
            requirements: ['productId' => RouteRequirements::UUID],
            status: 204,
            openapi: new Model\Operation(security: [['JWT' => []]]),
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            output: false,
            read: false,
            name: self::PREFIX_NAME . 'item-delete',
            processor: CartLineDeleteProcessor::class,
        ),
        new Delete(
            uriTemplate: '/cart',
            status: 204,
            openapi: new Model\Operation(security: [['JWT' => []]]),
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            output: false,
            name: self::PREFIX_NAME . 'delete',
            processor: CartDeleteProcessor::class,
        ),
    ],
    routePrefix: '/shop/me',
)]
final class CartResource
{
    private const string PREFIX_NAME = 'shop-cart-';

    #[Groups(['shop_cart:read'])]
    public ?string $id = null;

    /** @var CartLineResource[] */
    #[Groups(['shop_cart:read'])]
    public array $items = [];

    #[Groups(['shop_cart:read'])]
    public int $totalQuantity = 0;

    #[Groups(['shop_cart:read'])]
    public float $subtotal = 0.0;

    #[Groups(['shop_cart:read'])]
    public string $currency = 'EUR';

    #[Groups(['shop_cart:read'])]
    public ?DateTimeImmutable $createdAt = null;

    #[Groups(['shop_cart:read'])]
    public ?DateTimeImmutable $updatedAt = null;
}
