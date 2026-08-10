<?php

declare(strict_types=1);

namespace App\Presentation\Shop\ApiResource\Customer;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use App\Domain\Shop\Customer\ValueObject\CustomerStatus;
use App\Domain\User\ValueObject\Access\RoleSet;
use App\Infrastructure\Entity\Shop\Customer;
use App\Presentation\RouteRequirements;
use App\Presentation\Shop\Dto\Customer\CustomerPatchInput;
use App\Presentation\Shop\Dto\Customer\CustomerPostInput;
use App\Presentation\Shop\State\Customer\Customer\CustomerCollectionProvider;
use App\Presentation\Shop\State\Customer\Customer\CustomerGetProvider;
use App\Presentation\Shop\State\Customer\Customer\CustomerPatchProcessor;
use App\Presentation\Shop\State\Customer\Customer\CustomerPostProcessor;
use DateTimeImmutable;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    shortName: 'ShopCustomer',
    operations: [
        new Get(
            uriTemplate: '/customers/{id}',
            requirements: ['id' => RouteRequirements::UUID],
            openapi: new Model\Operation(
                security: [['JWT' => []]]
            ),
            name: self::PREFIX_NAME . 'get',
            provider: CustomerGetProvider::class,
        ),
        new Patch(
            uriTemplate: '/customers/{id}',
            requirements: ['id' => RouteRequirements::UUID],
            openapi: new Model\Operation(
                security: [['JWT' => []]]
            ),
            input: CustomerPatchInput::class,
            name: self::PREFIX_NAME . 'patch',
            processor: CustomerPatchProcessor::class,
        ),
        new GetCollection(
            uriTemplate: '/customers',
            openapi: new Model\Operation(
                security: [['JWT' => []]]
            ),
            paginationClientItemsPerPage: true,
            name: self::PREFIX_NAME . 'col',
            provider: CustomerCollectionProvider::class,
        ),
        new Post(
            uriTemplate: '/customers',
            openapi: new Model\Operation(
                security: [['JWT' => []]]
            ),
            input: CustomerPostInput::class,
            name: self::PREFIX_NAME . 'post',
            processor: CustomerPostProcessor::class,
        ),
    ],
    routePrefix: '/shop',
    order: ['createdAt' => 'DESC'],
    security: "is_granted('" . RoleSet::ROLE_ADMIN . "')",
    stateOptions: new Options(entityClass: Customer::class),
)]
#[ApiFilter(SearchFilter::class, properties: ['userAccountId' => 'exact', 'status' => 'exact'])]
#[ApiFilter(OrderFilter::class, properties: ['username', 'status', 'createdAt'])]
final class CustomerResource
{
    private const string PREFIX_NAME = 'shop-customers-';

    #[Groups(['shop_customer:read'])]
    public string $id;

    #[Groups(['shop_customer:read', 'shop_customer:write'])]
    public string $userAccountId;

    #[Groups(['shop_customer:read'])]
    public int $status = CustomerStatus::ACTIVE;

    #[Groups(['shop_customer:item:read'])]
    public int $nbAddress = 0;

    /** @var AddressResource[] */
    #[Groups(['shop_customer:item:read'])]
    public array $addresses = [];

    #[Groups(['shop_customer:read'])]
    public DateTimeImmutable $createdAt;

    #[Groups(['shop_customer:item:read'])]
    public DateTimeImmutable $updatedAt;
}
