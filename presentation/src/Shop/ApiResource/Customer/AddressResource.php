<?php

declare(strict_types=1);

namespace App\Presentation\Shop\ApiResource\Customer;

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
use App\Infrastructure\Entity\Shop\Address;
use App\Presentation\RouteRequirements;
use App\Presentation\Shop\Dto\Customer\Address\AddressPatchInput;
use App\Presentation\Shop\Dto\Customer\Address\AddressPostInput;
use App\Presentation\Shop\State\Customer\Address\AddressCollectionProvider;
use App\Presentation\Shop\State\Customer\Address\AddressDeleteProcessor;
use App\Presentation\Shop\State\Customer\Address\AddressGetProvider;
use App\Presentation\Shop\State\Customer\Address\AddressPatchProcessor;
use App\Presentation\Shop\State\Customer\Address\AddressPostProcessor;
use DateTimeImmutable;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    shortName: 'ShopAddress',
    operations: [
        new Get(
            uriTemplate: '/addresses/{id}',
            requirements: ['id' => RouteRequirements::UUID],
            openapi: new Model\Operation(
                security: [['ApiKeyAuth' => []]]
            ),
            security: "is_granted('shop_address:item:read', object)",
            name: self::PREFIX_NAME . 'me-get',
            provider: AddressGetProvider::class,
        ),
        new Patch(
            uriTemplate: '/addresses/{id}',
            requirements: ['id' => RouteRequirements::UUID],
            openapi: new Model\Operation(
                security: [['ApiKeyAuth' => []]]
            ),
            security: "is_granted('shop_address:item:write', object)",
            input: AddressPatchInput::class,
            name: self::PREFIX_NAME . 'me-patch',
            processor: AddressPatchProcessor::class,
        ),
        new Delete(
            uriTemplate: '/addresses/{id}',
            requirements: ['id' => RouteRequirements::UUID],
            status: 204,
            openapi: new Model\Operation(
                security: [['ApiKeyAuth' => []]]
            ),
            security: "is_granted('shop_address:item:write', object)",
            output: false,
            name: self::PREFIX_NAME . 'me-delete',
            processor: AddressDeleteProcessor::class,
        ),
        new Post(
            uriTemplate: '/addresses',
            openapi: new Model\Operation(
                security: [['ApiKeyAuth' => []]]
            ),
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            input: AddressPostInput::class,
            name: self::PREFIX_NAME . 'me-post',
            processor: AddressPostProcessor::class,
        ),
        new GetCollection(
            uriTemplate: '/addresses',
            openapi: new Model\Operation(
                security: [['ApiKeyAuth' => []]]
            ),
            paginationClientItemsPerPage: true,
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            name: self::PREFIX_NAME . 'me-col',
            provider: AddressCollectionProvider::class,
        ),
    ],
    routePrefix: '/shop/me',
    order: ['createdAt' => 'DESC'],
    stateOptions: new Options(entityClass: Address::class),
)]
#[ApiFilter(SearchFilter::class, properties: ['name' => 'partial', 'city' => 'partial', 'country' => 'exact'])]
#[ApiFilter(OrderFilter::class, properties: ['name', 'city', 'country', 'createdAt'])]
final class AddressResource
{
    private const string PREFIX_NAME = 'shop-addresses-';

    #[Groups(['shop_address:read', 'shop_customer:item:read'])]
    public string $id;

    #[Groups(['shop_address:read', 'shop_address:write', 'shop_customer:item:read'])]
    public string $name;

    #[Groups(['shop_address:read', 'shop_address:write', 'shop_customer:item:read'])]
    public string $firstname;

    #[Groups(['shop_address:read', 'shop_address:write', 'shop_customer:item:read'])]
    public string $lastname;

    #[Groups(['shop_address:read', 'shop_address:write', 'shop_customer:item:read'])]
    public ?string $company = null;

    #[Groups(['shop_address:read', 'shop_address:write', 'shop_customer:item:read'])]
    public string $address;

    #[Groups(['shop_address:read', 'shop_address:write', 'shop_customer:item:read'])]
    public string $zip;

    #[Groups(['shop_address:read', 'shop_address:write', 'shop_customer:item:read'])]
    public string $city;

    #[Groups(['shop_address:read', 'shop_address:write', 'shop_customer:item:read'])]
    public string $country;

    #[Groups(['shop_address:read', 'shop_address:write', 'shop_customer:item:read'])]
    public string $phone;

    #[Groups(['shop_address:read', 'shop_customer:item:read'])]
    public DateTimeImmutable $createdAt;

    #[Groups(['shop_address:read', 'shop_customer:item:read'])]
    public DateTimeImmutable $updatedAt;
}
