<?php

declare(strict_types=1);

namespace App\Presentation\Tests\Unit\State\User\Account\Me;

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
use App\Infrastructure\Entity\User\User;
use App\Presentation\User\ApiResource\UserResource;
use App\Presentation\User\Presenter\UserResourcePresenter;
use App\Presentation\User\State\Account\Me\UserMeProvider;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class UserMeProviderTest extends TestCase
{
    private Security&MockObject $security;

    private QueryBusInterface&MockObject $queryBus;

    private AvatarUrlResolverInterface&MockObject $avatarUrlResolver;

    private User&MockObject $user;

    private Operation&MockObject $operation;

    private UserMeProvider $provider;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->queryBus = $this->createMock(QueryBusInterface::class);
        $this->avatarUrlResolver = $this->createMock(AvatarUrlResolverInterface::class);
        $userResourcePresenter = new UserResourcePresenter($this->avatarUrlResolver);
        $this->user = $this->createMock(User::class);
        $this->operation = $this->createMock(Operation::class);
        $this->operation->expects($this->never())
            ->method('getName');

        $this->provider = new UserMeProvider(
            $this->security,
            $this->queryBus,
            $userResourcePresenter,
        );
    }

    public function testProvide(): void
    {
        $user = $this->user;
        $userId = Uuid::uuid4();
        $domainUser = $this->createDomainUser();
        $output = UserItem::fromUser($domainUser);

        $this->user->expects($this->once())
            ->method('getId')
            ->willReturn($userId);

        $this->security->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $this->queryBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(DisplayUserQuery::class))
            ->willReturn($output);

        $this->avatarUrlResolver->expects($this->once())
            ->method('resolve')
            ->willReturn('/uploads/avatar.jpg');

        $operation = $this->operation;
        $result = $this->provider->provide($operation);

        $this->assertInstanceOf(UserResource::class, $result);
    }

    public function testProvideThrowsAccessDeniedExceptionForUnauthenticatedUser(): void
    {
        $this->user->expects($this->never())
            ->method('getId');
        $this->queryBus->expects($this->never())
            ->method('dispatch');
        $this->avatarUrlResolver->expects($this->never())
            ->method('resolve');

        $this->security->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $this->expectException(AccessDeniedException::class);

        $operation = $this->operation;
        $this->provider->provide($operation);
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
