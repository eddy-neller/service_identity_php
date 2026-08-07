<?php

declare(strict_types=1);

namespace App\Presentation\Tests\Unit\State\User\UserManagement;

use ApiPlatform\Metadata\Operation;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\User\Port\AvatarUrlResolverInterface;
use App\Application\User\ReadModel\UserItem;
use App\Application\User\UseCase\Command\UserManagement\UpdateUserByAdmin\UpdateUserByAdminCommand;
use App\Domain\User\Model\User as DomainUser;
use App\Domain\User\ValueObject\Identity\EmailAddress;
use App\Domain\User\ValueObject\Identity\UserId;
use App\Domain\User\ValueObject\Identity\Username;
use App\Domain\User\ValueObject\Profile\Preferences;
use App\Domain\User\ValueObject\Security\HashedPassword;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\User\ApiResource\UserResource;
use App\Presentation\User\Dto\UserManagement\UserPatchInput;
use App\Presentation\User\Presenter\UserResourcePresenter;
use App\Presentation\User\State\UserManagement\UserPatchProcessor;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use stdClass;

final class UserPatchProcessorTest extends TestCase
{
    private CommandBusInterface&MockObject $commandBus;

    private AvatarUrlResolverInterface&MockObject $avatarUrlResolver;

    private Operation&MockObject $operation;

    private UserPatchProcessor $userPatchProcessor;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(CommandBusInterface::class);
        $this->avatarUrlResolver = $this->createMock(AvatarUrlResolverInterface::class);
        $userResourcePresenter = new UserResourcePresenter($this->avatarUrlResolver);
        $this->operation = $this->createMock(Operation::class);
        $this->operation->expects($this->never())
            ->method('getName');

        $this->userPatchProcessor = new UserPatchProcessor(
            $this->commandBus,
            $userResourcePresenter,
        );
    }

    public function testProcessWithValidInput(): void
    {
        $input = $this->createValidUserPatchInput();
        $userId = Uuid::uuid4()->toString();
        $uriVariables = ['id' => $userId];
        $domainUser = $this->createDomainUser();
        $output = UserItem::fromUser($domainUser);

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($command) use ($userId, $input, $output): UserItem {
                $this->assertInstanceOf(UpdateUserByAdminCommand::class, $command);
                $this->assertSame($userId, $command->userId);
                $this->assertSame($input->email, $command->email);
                $this->assertSame($input->username, $command->username);
                $this->assertSame($input->roles, $command->roles);
                $this->assertSame($input->status, $command->status);

                return $output;
            });

        $this->avatarUrlResolver->expects($this->once())
            ->method('resolve')
            ->willReturn('/uploads/avatar.jpg');

        $result = $this->userPatchProcessor->process($input, $this->operation, $uriVariables);

        $this->assertInstanceOf(UserResource::class, $result);
    }

    public function testProcessThrowsLogicExceptionForInvalidInput(): void
    {
        $invalidInput = new stdClass();

        $this->commandBus->expects($this->never())
            ->method('dispatch');
        $this->avatarUrlResolver->expects($this->never())
            ->method('resolve');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->userPatchProcessor->process($invalidInput, $this->operation);
    }

    public function testProcessThrowsLogicExceptionForNullInput(): void
    {
        $this->commandBus->expects($this->never())
            ->method('dispatch');
        $this->avatarUrlResolver->expects($this->never())
            ->method('resolve');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->userPatchProcessor->process(null, $this->operation);
    }

    public function testProcessThrowsLogicExceptionForStringInput(): void
    {
        $this->commandBus->expects($this->never())
            ->method('dispatch');
        $this->avatarUrlResolver->expects($this->never())
            ->method('resolve');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->userPatchProcessor->process('invalid', $this->operation);
    }

    public function testProcessThrowsLogicExceptionWhenUriVariableMissing(): void
    {
        $input = $this->createValidUserPatchInput();

        $this->commandBus->expects($this->never())
            ->method('dispatch');
        $this->avatarUrlResolver->expects($this->never())
            ->method('resolve');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->userPatchProcessor->process($input, $this->operation, []);
    }

    public function testProcessThrowsLogicExceptionWhenUriVariableIsNotString(): void
    {
        $input = $this->createValidUserPatchInput();

        $this->commandBus->expects($this->never())
            ->method('dispatch');
        $this->avatarUrlResolver->expects($this->never())
            ->method('resolve');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->userPatchProcessor->process($input, $this->operation, ['id' => 123]);
    }

    public function testProcessWithDifferentUserId(): void
    {
        $input = $this->createValidUserPatchInput();
        $userId = Uuid::uuid4()->toString();
        $uriVariables = ['id' => $userId];
        $domainUser = $this->createDomainUser();
        $output = UserItem::fromUser($domainUser);

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturn($output);

        $this->avatarUrlResolver->expects($this->once())
            ->method('resolve')
            ->willReturn('/uploads/avatar.jpg');

        $result = $this->userPatchProcessor->process($input, $this->operation, $uriVariables);

        $this->assertInstanceOf(UserResource::class, $result);
    }

    public function testProcessReturnsUpdatedUser(): void
    {
        $input = $this->createValidUserPatchInput();
        $userId = Uuid::uuid4()->toString();
        $uriVariables = ['id' => $userId];
        $domainUser = $this->createDomainUser();
        $output = UserItem::fromUser($domainUser);

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturn($output);

        $this->avatarUrlResolver->expects($this->once())
            ->method('resolve')
            ->willReturn('/uploads/avatar.jpg');

        $result = $this->userPatchProcessor->process($input, $this->operation, $uriVariables);

        $this->assertInstanceOf(UserResource::class, $result);
    }

    public function testProcessWithPartialUpdate(): void
    {
        $input = new UserPatchInput();
        $input->username = 'updatedusername';

        $userId = Uuid::uuid4()->toString();
        $uriVariables = ['id' => $userId];
        $domainUser = $this->createDomainUser();
        $output = UserItem::fromUser($domainUser);

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturn($output);

        $this->avatarUrlResolver->expects($this->once())
            ->method('resolve')
            ->willReturn('/uploads/avatar.jpg');

        $result = $this->userPatchProcessor->process($input, $this->operation, $uriVariables);

        $this->assertInstanceOf(UserResource::class, $result);
    }

    private function createValidUserPatchInput(): UserPatchInput
    {
        $input = new UserPatchInput();
        $input->username = 'updateduser';
        $input->email = 'updated@example.com';
        $input->roles = ['ROLE_ADMIN'];
        $input->status = 1;

        return $input;
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
