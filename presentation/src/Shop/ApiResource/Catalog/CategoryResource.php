<?php

declare(strict_types=1);

namespace App\Presentation\Shop\ApiResource\Catalog;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use App\Domain\User\ValueObject\Security\RoleSet;
use App\Infrastructure\Entity\Shop\Category;
use App\Presentation\RouteRequirements;
use App\Presentation\Shop\Dto\Catalog\Category\CategoryPatchInput;
use App\Presentation\Shop\Dto\Catalog\Category\CategoryPostInput;
use App\Presentation\Shop\State\Catalog\Category\CategoryCollectionProvider;
use App\Presentation\Shop\State\Catalog\Category\CategoryDeleteProcessor;
use App\Presentation\Shop\State\Catalog\Category\CategoryGetProvider;
use App\Presentation\Shop\State\Catalog\Category\CategoryPatchProcessor;
use App\Presentation\Shop\State\Catalog\Category\CategoryPostProcessor;
use DateTimeImmutable;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\MaxDepth;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

#[ApiResource(
    shortName: 'ShopCategory',
    operations: [
        new Get(
            uriTemplate: '/categories/{id}',
            requirements: ['id' => RouteRequirements::UUID],
            cacheHeaders: [
                'max_age' => 21600,
                'shared_max_age' => 86400,
            ],
            name: self::PREFIX_NAME . 'get',
            provider: CategoryGetProvider::class,
        ),
        new Patch(
            uriTemplate: '/categories/{id}',
            requirements: ['id' => RouteRequirements::UUID],
            openapi: new Model\Operation(
                security: [['JWT' => []]]
            ),
            security: "is_granted('" . RoleSet::ROLE_ADMIN . "')",
            input: CategoryPatchInput::class,
            name: self::PREFIX_NAME . 'patch',
            processor: CategoryPatchProcessor::class,
        ),
        new Delete(
            uriTemplate: '/categories/{id}',
            requirements: ['id' => RouteRequirements::UUID],
            status: 204,
            openapi: new Model\Operation(
                security: [['JWT' => []]]
            ),
            security: "is_granted('" . RoleSet::ROLE_ADMIN . "')",
            output: false,
            name: self::PREFIX_NAME . 'delete',
            processor: CategoryDeleteProcessor::class,
        ),
        new GetCollection(
            uriTemplate: '/categories',
            cacheHeaders: [
                'max_age' => 21600,
                'shared_max_age' => 86400,
            ],
            openapi: new Model\Operation(
                parameters: [
                    new Model\Parameter(
                        name: 'level',
                        in: 'query',
                        required: false,
                        schema: [
                            'type' => 'integer',
                        ],
                    ),
                    new Model\Parameter(
                        name: 'parent',
                        in: 'query',
                        required: false,
                        schema: [
                            'type' => 'string',
                            'format' => 'uuid',
                        ],
                    ),
                ]
            ),
            paginationClientItemsPerPage: true,
            name: self::PREFIX_NAME . 'col',
            provider: CategoryCollectionProvider::class,
        ),
        new Post(
            uriTemplate: '/categories',
            openapi: new Model\Operation(
                security: [['JWT' => []]]
            ),
            security: "is_granted('" . RoleSet::ROLE_ADMIN . "')",
            input: CategoryPostInput::class,
            name: self::PREFIX_NAME . 'post',
            processor: CategoryPostProcessor::class,
        ),
    ],
    routePrefix: '/shop',
    normalizationContext: [AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true],
    stateOptions: new Options(entityClass: Category::class),
)]
#[ApiFilter(SearchFilter::class, properties: ['level' => 'exact', 'parent' => 'exact'])]
#[ApiFilter(OrderFilter::class, properties: ['title', 'level', 'nbProduct', 'createdAt'])]
final class CategoryResource
{
    private const string PREFIX_NAME = 'shop-categories-';

    #[Groups(['shop_category:read', 'shop_product:read'])]
    public string $id;

    #[Groups(['shop_category:read', 'shop_category:write', 'shop_product:read'])]
    public string $title;

    #[Groups(['shop_category:item:read', 'shop_category:write'])]
    public ?string $description = null;

    #[Groups(['shop_category:read', 'shop_product:item:read'])]
    public int $nbProduct = 0;

    #[Groups(['shop_category:read'])]
    public string $slug;

    #[Groups(['shop_category:item:read', 'shop_category:write'])]
    #[MaxDepth(1)]
    public ?self $parent = null;

    #[Groups(['shop_category:item:read'])]
    #[MaxDepth(1)]
    public ?array $children = null;

    #[Groups(['shop_category:read', 'shop_product:item:read'])]
    public int $level = 0;

    #[Groups(['shop_category:read'])]
    public bool $hasChildren = false;

    #[Groups(['shop_category:read'])]
    public DateTimeImmutable $createdAt;

    #[Groups(['shop_category:item:read'])]
    public DateTimeImmutable $updatedAt;
}
