<?php

declare(strict_types=1);

namespace App\Presentation\Tests\Unit\State\User\UserManagement;

use ApiPlatform\Metadata\GetCollection;
use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\User\Port\AvatarUrlResolverInterface;
use App\Application\User\ReadModel\UserItem;
use App\Application\User\ReadModel\UserList;
use App\Application\User\UseCase\Query\UserManagement\DisplayListUser\DisplayListUserQuery;
use App\Domain\User\Model\User as DomainUser;
use App\Domain\User\ValueObject\Identity\EmailAddress;
use App\Domain\User\ValueObject\Identity\UserId;
use App\Domain\User\ValueObject\Identity\Username;
use App\Domain\User\ValueObject\Profile\Preferences;
use App\Domain\User\ValueObject\Security\HashedPassword;
use App\Presentation\User\ApiResource\UserResource;
use App\Presentation\User\Presenter\UserResourcePresenter;
use App\Presentation\User\State\UserManagement\UserAdminCollectionProvider;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(UserAdminCollectionProvider::class)]
final class UserAdminCollectionProviderTest extends TestCase
{
    public function testItMapsUsersToUserResourceAndKeepsPaginationAttributes(): void
    {
        $request = new Request();
        $queryBus = $this->createMock(QueryBusInterface::class);
        $domainUser = $this->createDomainUser();
        $output = new UserList([UserItem::fromUser($domainUser)], 1, 1);

        $queryBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($query) use ($output): UserList {
                $this->assertInstanceOf(DisplayListUserQuery::class, $query);
                $this->assertSame('2', $query->page);
                $this->assertSame('15', $query->itemsPerPage);
                $this->assertSame('john', $query->filters['username'] ?? null);
                $this->assertSame('john@example.com', $query->filters['email'] ?? null);
                $this->assertSame(['createdAt' => 'ASC'], $query->orderBy);

                return $output;
            });

        $avatarUrlResolver = $this->createMock(AvatarUrlResolverInterface::class);
        $avatarUrlResolver
            ->expects($this->once())
            ->method('resolve')
            ->with(self::callback(static function (?string $avatarName): bool {
                return 'avatar.jpg' === $avatarName;
            }))
            ->willReturn('/uploads/images/user/avatar/avatar.jpg');

        $provider = new UserAdminCollectionProvider($queryBus, new UserResourcePresenter($avatarUrlResolver));

        $result = $provider->provide(
            new GetCollection(name: 'users-admin-col'),
            context: [
                'request' => $request,
                'filters' => [
                    'page' => '2',
                    'itemsPerPage' => '15',
                    'username' => 'john',
                    'email' => 'john@example.com',
                    'order' => [
                        'createdAt' => 'asc',
                    ],
                ],
            ],
        );

        $this->assertCount(1, $result);
        $this->assertInstanceOf(UserResource::class, $result[0]);
        $this->assertSame('john', $result[0]->username);
        $this->assertSame('/uploads/images/user/avatar/avatar.jpg', $result[0]->avatarUrl);
        $this->assertSame(1, $request->attributes->get('_total_items'));
        $this->assertSame(1, $request->attributes->get('_total_pages'));
    }

    public function testItHandlesInvalidFiltersWithoutRequest(): void
    {
        $queryBus = $this->createMock(QueryBusInterface::class);
        $domainUser = $this->createDomainUser();
        $output = new UserList([UserItem::fromUser($domainUser)], 2, 3);

        $queryBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($query) use ($output): UserList {
                $this->assertInstanceOf(DisplayListUserQuery::class, $query);
                $this->assertNull($query->page);
                $this->assertNull($query->itemsPerPage);
                $this->assertSame([], $query->filters);
                $this->assertSame([], $query->orderBy);

                return $output;
            });

        $avatarUrlResolver = $this->createMock(AvatarUrlResolverInterface::class);
        $avatarUrlResolver
            ->expects($this->once())
            ->method('resolve')
            ->with(self::callback(static function (?string $avatarName): bool {
                return 'avatar.jpg' === $avatarName;
            }))
            ->willReturn('/uploads/images/user/avatar/avatar.jpg');

        $provider = new UserAdminCollectionProvider($queryBus, new UserResourcePresenter($avatarUrlResolver));

        $result = $provider->provide(
            new GetCollection(name: 'users-admin-col'),
            context: [
                'filters' => 'not-an-array',
            ],
        );

        $this->assertCount(1, $result);
        $this->assertInstanceOf(UserResource::class, $result[0]);
        $this->assertSame('john', $result[0]->username);
        $this->assertSame('/uploads/images/user/avatar/avatar.jpg', $result[0]->avatarUrl);
    }

    public function testItNormalizesMultipleValidSortCriteriaInCanonicalPriorityOrder(): void
    {
        $queryBus = $this->createMock(QueryBusInterface::class);
        $output = new UserList([], 0, 0);

        $queryBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($query) use ($output): UserList {
                $this->assertInstanceOf(DisplayListUserQuery::class, $query);
                $this->assertSame([
                    'username' => 'ASC',
                    'email' => 'DESC',
                    'createdAt' => 'ASC',
                ], $query->orderBy);

                return $output;
            });

        $avatarUrlResolver = $this->createStub(AvatarUrlResolverInterface::class);
        $provider = new UserAdminCollectionProvider($queryBus, new UserResourcePresenter($avatarUrlResolver));

        $result = $provider->provide(
            new GetCollection(name: 'users-admin-col'),
            context: [
                'filters' => [
                    'order' => [
                        'createdAt' => 'asc',
                        'unsupported' => 'DESC',
                        'email' => 'desc',
                        'username' => 'ASC',
                    ],
                ],
            ],
        );

        $this->assertSame([], $result);
    }

    private function createDomainUser(): DomainUser
    {
        $now = new DateTimeImmutable('2025-01-01 10:00:00');
        $user = DomainUser::register(
            id: UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            username: Username::fromString('john'),
            email: EmailAddress::fromString('john@example.com'),
            password: HashedPassword::fromString('hash'),
            preferences: Preferences::fromArray(['lang' => 'fr']),
            now: $now,
        );

        $user->updateAvatar('avatar.jpg', $now);

        return $user;
    }
}
