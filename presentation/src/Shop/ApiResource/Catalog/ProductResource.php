<?php

declare(strict_types=1);

namespace App\Presentation\Shop\ApiResource\Catalog;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use ApiPlatform\OpenApi\Model\RequestBody;
use App\Domain\User\ValueObject\Security\RoleSet;
use App\Infrastructure\Entity\Shop\Product;
use App\Presentation\RouteRequirements;
use App\Presentation\Shop\Dto\Catalog\Product\ProductImageInput;
use App\Presentation\Shop\Dto\Catalog\Product\ProductPatchInput;
use App\Presentation\Shop\Dto\Catalog\Product\ProductPostInput;
use App\Presentation\Shop\State\Catalog\Product\ProductCollectionProvider;
use App\Presentation\Shop\State\Catalog\Product\ProductDeleteProcessor;
use App\Presentation\Shop\State\Catalog\Product\ProductGetProvider;
use App\Presentation\Shop\State\Catalog\Product\ProductImageProcessor;
use App\Presentation\Shop\State\Catalog\Product\ProductPatchProcessor;
use App\Presentation\Shop\State\Catalog\Product\ProductPostProcessor;
use ArrayObject;
use DateTimeImmutable;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    shortName: 'ShopProduct',
    operations: [
        new Get(
            uriTemplate: '/products/{id}',
            requirements: ['id' => RouteRequirements::UUID],
            cacheHeaders: [
                'max_age' => 21600,
                'shared_max_age' => 86400,
            ],
            name: self::PREFIX_NAME . 'get',
            provider: ProductGetProvider::class,
        ),
        new Patch(
            uriTemplate: '/products/{id}',
            requirements: ['id' => RouteRequirements::UUID],
            openapi: new Model\Operation(
                security: [['JWT' => []]]
            ),
            security: "is_granted('" . RoleSet::ROLE_ADMIN . "')",
            input: ProductPatchInput::class,
            name: self::PREFIX_NAME . 'patch',
            processor: ProductPatchProcessor::class,
        ),
        new Delete(
            uriTemplate: '/products/{id}',
            requirements: ['id' => RouteRequirements::UUID],
            status: 204,
            openapi: new Model\Operation(
                security: [['JWT' => []]]
            ),
            security: "is_granted('" . RoleSet::ROLE_ADMIN . "')",
            output: false,
            name: self::PREFIX_NAME . 'delete',
            processor: ProductDeleteProcessor::class,
        ),
        new Post(
            uriTemplate: '/products',
            openapi: new Model\Operation(
                security: [['JWT' => []]]
            ),
            security: "is_granted('" . RoleSet::ROLE_ADMIN . "')",
            input: ProductPostInput::class,
            name: self::PREFIX_NAME . 'post',
            processor: ProductPostProcessor::class,
        ),
        new GetCollection(
            uriTemplate: '/products',
            cacheHeaders: [
                'max_age' => 21600,
                'shared_max_age' => 86400,
            ],
            paginationClientItemsPerPage: true,
            name: self::PREFIX_NAME . 'col',
            provider: ProductCollectionProvider::class,
        ),
        new Post(
            uriTemplate: '/products/{id}/image',
            inputFormats: ['multipart' => ['multipart/form-data']],
            requirements: ['id' => RouteRequirements::UUID],
            openapi: new Model\Operation(
                summary: 'Creates the Image of a Shop Product resource',
                requestBody: new RequestBody(
                    content: new ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'imageFile' => [
                                        'type' => 'string',
                                        'format' => 'binary',
                                        'description' => 'Shop product image',
                                    ],
                                ],
                            ],
                        ],
                    ]),
                ),
                security: [['JWT' => []]]
            ),
            security: "is_granted('" . RoleSet::ROLE_ADMIN . "')",
            input: ProductImageInput::class,
            name: self::PREFIX_NAME . 'image',
            processor: ProductImageProcessor::class,
        ),
    ],
    routePrefix: '/shop',
    order: ['createdAt' => 'DESC'],
    stateOptions: new Options(entityClass: Product::class),
)]
#[ApiFilter(SearchFilter::class, properties: ['title' => 'partial', 'description' => 'partial'])]
#[ApiFilter(OrderFilter::class, properties: ['title', 'category.title', 'price', 'createdAt'])]
final class ProductResource
{
    private const string PREFIX_NAME = 'shop-products-';

    #[Groups(['shop_product:read'])]
    public string $id;

    #[Groups(['shop_product:read', 'shop_product:write'])]
    public string $title;

    #[Groups(['shop_product:item:read', 'shop_product:write'])]
    public string $subtitle;

    #[Groups(['shop_product:item:read', 'shop_product:write'])]
    public string $description;

    #[Groups(['shop_product:read', 'shop_product:write'])]
    public float $price;

    #[Groups(['shop_product:read'])]
    public string $slug;

    #[Groups(['shop_product:read'])]
    public ?string $imageUrl = null;

    #[ApiFilter(filterClass: SearchFilter::class, strategy: 'exact')]
    #[ApiProperty(security: "is_granted('ROLE_ADMIN')")]
    #[Groups(['shop_product:read', 'shop_product:write'])]
    public CategoryResource $category;

    #[Groups(['shop_product:read'])]
    public DateTimeImmutable $createdAt;

    #[Groups(['shop_product:item:read'])]
    public DateTimeImmutable $updatedAt;
}
