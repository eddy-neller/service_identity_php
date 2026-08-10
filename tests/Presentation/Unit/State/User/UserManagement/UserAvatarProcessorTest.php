<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Unit\State\User\UserManagement;

use ApiPlatform\Metadata\Operation;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\Shared\Port\FileInterface;
use App\Application\User\Port\AvatarUrlResolverInterface;
use App\Application\User\ReadModel\UserItem;
use App\Application\User\UseCase\Command\Account\UpdateAvatar\UpdateAvatarCommand;
use App\Domain\User\Model\User as DomainUser;
use App\Domain\User\ValueObject\Identity\EmailAddress;
use App\Domain\User\ValueObject\Identity\UserId;
use App\Domain\User\ValueObject\Identity\Username;
use App\Domain\User\ValueObject\Profile\Preferences;
use App\Domain\User\ValueObject\Security\HashedPassword;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\User\ApiResource\UserResource;
use App\Presentation\User\Dto\UserManagement\UserAvatarInput;
use App\Presentation\User\Presenter\UserResourcePresenter;
use App\Presentation\User\State\UserManagement\UserAvatarProcessor;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use stdClass;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class UserAvatarProcessorTest extends TestCase
{
    private CommandBusInterface&MockObject $commandBus;

    private AvatarUrlResolverInterface&MockObject $avatarUrlResolver;

    private Operation&MockObject $operation;

    private UserAvatarProcessor $userAvatarProcessor;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(CommandBusInterface::class);
        $this->avatarUrlResolver = $this->createMock(AvatarUrlResolverInterface::class);
        $userResourcePresenter = new UserResourcePresenter($this->avatarUrlResolver);
        $this->operation = $this->createMock(Operation::class);
        $this->operation->expects($this->never())
            ->method('getName');

        $this->userAvatarProcessor = new UserAvatarProcessor(
            $this->commandBus,
            $userResourcePresenter,
        );
    }

    public function testProcessWithValidInput(): void
    {
        $input = $this->createValidUserAvatarInput(true);
        $userId = Uuid::uuid4()->toString();
        $uriVariables = ['id' => $userId];
        $domainUser = $this->createDomainUser();
        $output = UserItem::fromUser($domainUser);

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($command) use ($userId, $output): UserItem {
                $this->assertInstanceOf(UpdateAvatarCommand::class, $command);
                $this->assertSame($userId, $command->userId);
                $this->assertInstanceOf(FileInterface::class, $command->avatarFile);
                $this->assertSame('avatar.jpg', $command->avatarFile->getClientOriginalName());
                $this->assertTrue($command->avatarFile->isValid());

                return $output;
            });

        $this->avatarUrlResolver->expects($this->once())
            ->method('resolve')
            ->willReturn('/uploads/avatar.jpg');

        $result = $this->userAvatarProcessor->process($input, $this->operation, $uriVariables);

        $this->assertInstanceOf(UserResource::class, $result);
    }

    public function testProcessThrowsLogicExceptionForInvalidInput(): void
    {
        $invalidInput = new stdClass();
        $uriVariables = ['id' => 'test-id'];

        $this->commandBus->expects($this->never())
            ->method('dispatch');
        $this->avatarUrlResolver->expects($this->never())
            ->method('resolve');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->userAvatarProcessor->process($invalidInput, $this->operation, $uriVariables);
    }

    public function testProcessThrowsLogicExceptionForNullInput(): void
    {
        $uriVariables = ['id' => 'test-id'];

        $this->commandBus->expects($this->never())
            ->method('dispatch');
        $this->avatarUrlResolver->expects($this->never())
            ->method('resolve');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->userAvatarProcessor->process(null, $this->operation, $uriVariables);
    }

    public function testProcessThrowsLogicExceptionForStringInput(): void
    {
        $uriVariables = ['id' => 'test-id'];

        $this->commandBus->expects($this->never())
            ->method('dispatch');
        $this->avatarUrlResolver->expects($this->never())
            ->method('resolve');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->userAvatarProcessor->process('invalid', $this->operation, $uriVariables);
    }

    public function testProcessThrowsLogicExceptionWhenAvatarFileIsMissing(): void
    {
        $input = new UserAvatarInput();
        $uriVariables = ['id' => Uuid::uuid4()->toString()];

        $this->commandBus->expects($this->never())
            ->method('dispatch');
        $this->avatarUrlResolver->expects($this->never())
            ->method('resolve');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->userAvatarProcessor->process($input, $this->operation, $uriVariables);
    }

    public function testProcessThrowsLogicExceptionWhenUriVariableMissing(): void
    {
        $input = $this->createValidUserAvatarInput(false);

        $this->commandBus->expects($this->never())
            ->method('dispatch');
        $this->avatarUrlResolver->expects($this->never())
            ->method('resolve');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->userAvatarProcessor->process($input, $this->operation, []);
    }

    public function testProcessThrowsLogicExceptionWhenUriVariableIsNotString(): void
    {
        $input = $this->createValidUserAvatarInput(false);

        $this->commandBus->expects($this->never())
            ->method('dispatch');
        $this->avatarUrlResolver->expects($this->never())
            ->method('resolve');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->userAvatarProcessor->process($input, $this->operation, ['id' => 123]);
    }

    private function createValidUserAvatarInput(bool $shouldBeUsed): UserAvatarInput
    {
        $input = new UserAvatarInput();
        /** @var UploadedFile&MockObject $mockFile */
        $mockFile = $this->createMock(UploadedFile::class);
        if ($shouldBeUsed) {
            $mockFile->expects($this->once())
                ->method('getClientOriginalName')
                ->willReturn('avatar.jpg');
            $mockFile->expects($this->never())
                ->method('getClientOriginalExtension');
            $mockFile->expects($this->once())
                ->method('isValid')
                ->willReturn(true);
        } else {
            $mockFile->expects($this->never())
                ->method('getClientOriginalName');
            $mockFile->expects($this->never())
                ->method('getClientOriginalExtension');
            $mockFile->expects($this->never())
                ->method('isValid');
        }

        $input->avatarFile = $mockFile;

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
