<?php

declare(strict_types=1);

namespace App\Presentation\Tests\Unit\State\User\UserManagement;

use ApiPlatform\Metadata\Operation;
use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\User\Port\AvatarUrlResolverInterface;
use App\Application\User\ReadModel\UserItem;
use App\Application\User\UseCase\Query\Account\DisplayUser\DisplayUserQuery;
use App\Domain\User\Model\User as DomainUser;
use App\Domain\User\ValueObject\Identity\EmailAddress;
use App\Domain\User\ValueObject\Identity\UserId;
use App\Domain\User\ValueObject\Identity\Username;
use App\Domain\User\ValueObject\Profile\Preferences;
use App\Domain\User\ValueObject\Security\HashedPassword;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\User\ApiResource\UserResource;
use App\Presentation\User\Presenter\UserResourcePresenter;
use App\Presentation\User\State\UserManagement\UserGetProvider;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class UserGetProviderTest extends TestCase
{
    private QueryBusInterface&MockObject $queryBus;

    private AvatarUrlResolverInterface&MockObject $avatarUrlResolver;

    private Operation&MockObject $operation;

    private UserGetProvider $provider;

    protected function setUp(): void
    {
        $this->queryBus = $this->createMock(QueryBusInterface::class);
        $this->avatarUrlResolver = $this->createMock(AvatarUrlResolverInterface::class);
        $userResourcePresenter = new UserResourcePresenter($this->avatarUrlResolver);
        $this->operation = $this->createMock(Operation::class);
        $this->operation->expects($this->never())
            ->method('getName');

        $this->provider = new UserGetProvider(
            $this->queryBus,
            $userResourcePresenter,
        );
    }

    public function testProvide(): void
    {
        $userId = Uuid::uuid4()->toString();
        $domainUser = $this->createDomainUser();
        $output = UserItem::fromUser($domainUser);

        $this->queryBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($query) use ($userId, $output): UserItem {
                $this->assertInstanceOf(DisplayUserQuery::class, $query);
                $this->assertSame($userId, $query->userId);

                return $output;
            });

        $this->avatarUrlResolver->expects($this->once())
            ->method('resolve')
            ->willReturn('/uploads/avatar.jpg');

        $result = $this->provider->provide(
            $this->operation,
            ['id' => $userId]
        );

        $this->assertInstanceOf(UserResource::class, $result);
    }

    public function testProvideThrowsLogicExceptionWhenIdIsMissing(): void
    {
        $this->queryBus->expects($this->never())
            ->method('dispatch');
        $this->avatarUrlResolver->expects($this->never())
            ->method('resolve');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->provider->provide($this->operation, []);
    }

    public function testProvideThrowsLogicExceptionWhenIdIsNull(): void
    {
        $this->queryBus->expects($this->never())
            ->method('dispatch');
        $this->avatarUrlResolver->expects($this->never())
            ->method('resolve');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->provider->provide(
            $this->operation,
            ['id' => null]
        );
    }

    private function createDomainUser(): DomainUser
    {
        return DomainUser::register(
            id: UserId::fromString(Uuid::uuid4()->toString()),
            username: Username::fromString('testuser'),
            email: EmailAddress::fromString('test@example.com'),
            password: HashedPassword::fromString('hash'),
            preferences: Preferences::fromArray(['lang' => 'fr']),
            now: new DateTimeImmutable(),
        );
    }
}
