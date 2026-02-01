<?php

declare(strict_types=1);

namespace App\Presentation\Tests\Unit\State\User;

use ApiPlatform\Metadata\Operation;
use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\User\Port\AvatarUrlResolverInterface;
use App\Application\User\UseCase\Query\DisplayUser\DisplayUserOutput;
use App\Application\User\UseCase\Query\DisplayUser\DisplayUserQuery;
use App\Domain\User\Identity\ValueObject\EmailAddress;
use App\Domain\User\Identity\ValueObject\UserId;
use App\Domain\User\Identity\ValueObject\Username;
use App\Domain\User\Model\User as DomainUser;
use App\Domain\User\Preference\ValueObject\Preferences;
use App\Domain\User\Security\ValueObject\HashedPassword;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\User\ApiResource\UserResource;
use App\Presentation\User\Presenter\UserResourcePresenter;
use App\Presentation\User\State\UserGetProvider;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class UserGetProviderTest extends KernelTestCase
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
        $output = new DisplayUserOutput($domainUser);

        $this->queryBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($query) use ($userId, $output): DisplayUserOutput {
                $this->assertInstanceOf(DisplayUserQuery::class, $query);
                $this->assertSame($userId, $query->userId->toString());

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
            username: new Username('testuser'),
            email: new EmailAddress('test@example.com'),
            password: new HashedPassword('hash'),
            preferences: Preferences::fromArray(['lang' => 'fr']),
            now: new DateTimeImmutable(),
        );
    }
}
