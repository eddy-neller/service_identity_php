<?php

declare(strict_types=1);

namespace App\Presentation\Tests\Unit\State\User\Me;

use ApiPlatform\Metadata\Operation;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\Shared\Port\FileInterface;
use App\Application\User\Port\AvatarUrlResolverInterface;
use App\Application\User\UseCase\Command\UpdateAvatar\UpdateAvatarCommand;
use App\Application\User\UseCase\Command\UpdateAvatar\UpdateAvatarOutput;
use App\Domain\User\Model\User as DomainUser;
use App\Domain\User\ValueObject\EmailAddress;
use App\Domain\User\ValueObject\Preferences;
use App\Domain\User\ValueObject\Security\HashedPassword;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\Username;
use App\Infrastructure\Entity\User\User;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\User\ApiResource\UserResource;
use App\Presentation\User\Dto\Me\UserMeAvatarInput;
use App\Presentation\User\Presenter\UserResourcePresenter;
use App\Presentation\User\State\Me\UserMeAvatarProcessor;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use Ramsey\Uuid\Uuid;
use stdClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class UserMeAvatarProcessorTest extends KernelTestCase
{
    private Security&MockObject $security;

    private CommandBusInterface&MockObject $commandBus;

    private AvatarUrlResolverInterface&MockObject $avatarUrlResolver;

    private Operation&MockObject $operation;

    private User&MockObject $user;

    private UserMeAvatarProcessor $userMeAvatarProcessor;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->commandBus = $this->createMock(CommandBusInterface::class);
        $this->avatarUrlResolver = $this->createMock(AvatarUrlResolverInterface::class);
        $userResourcePresenter = new UserResourcePresenter($this->avatarUrlResolver);
        $this->operation = $this->createMock(Operation::class);
        $this->operation->expects($this->never())
            ->method('getName');
        $this->user = $this->createMock(User::class);

        $this->userMeAvatarProcessor = new UserMeAvatarProcessor(
            $this->security,
            $this->commandBus,
            $userResourcePresenter,
        );
    }

    public function testProcessWithValidInput(): void
    {
        $input = $this->createValidUserMeAvatarInput();
        $userId = Uuid::uuid4();
        $domainUser = $this->createDomainUser();
        $output = new UpdateAvatarOutput($domainUser);

        $this->user->expects($this->once())
            ->method('getId')
            ->willReturn($userId);

        $this->security->expects($this->once())
            ->method('getUser')
            ->willReturn($this->user);

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($command) use ($output): UpdateAvatarOutput {
                $this->assertInstanceOf(UpdateAvatarCommand::class, $command);
                $this->assertInstanceOf(FileInterface::class, $command->avatarFile);
                $this->assertSame('avatar.jpg', $command->avatarFile->getClientOriginalName());
                $this->assertTrue($command->avatarFile->isValid());

                return $output;
            });

        $this->avatarUrlResolver->expects($this->once())
            ->method('resolve')
            ->willReturn('/uploads/avatar.jpg');

        $result = $this->userMeAvatarProcessor->process($input, $this->operation);

        $this->assertInstanceOf(UserResource::class, $result);
    }

    public function testProcessThrowsLogicExceptionForInvalidInput(): void
    {
        $invalidInput = new stdClass();

        $this->security->expects($this->never())
            ->method('getUser');
        $this->user->expects($this->never())
            ->method('getId');
        $this->commandBus->expects($this->never())
            ->method('dispatch');
        $this->avatarUrlResolver->expects($this->never())
            ->method('resolve');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->userMeAvatarProcessor->process($invalidInput, $this->operation);
    }

    public function testProcessThrowsLogicExceptionForNullInput(): void
    {
        $this->security->expects($this->never())
            ->method('getUser');
        $this->user->expects($this->never())
            ->method('getId');
        $this->commandBus->expects($this->never())
            ->method('dispatch');
        $this->avatarUrlResolver->expects($this->never())
            ->method('resolve');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->userMeAvatarProcessor->process(null, $this->operation);
    }

    public function testProcessThrowsLogicExceptionForStringInput(): void
    {
        $this->security->expects($this->never())
            ->method('getUser');
        $this->user->expects($this->never())
            ->method('getId');
        $this->commandBus->expects($this->never())
            ->method('dispatch');
        $this->avatarUrlResolver->expects($this->never())
            ->method('resolve');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->userMeAvatarProcessor->process('invalid', $this->operation);
    }

    public function testProcessThrowsLogicExceptionForArrayInput(): void
    {
        $this->security->expects($this->never())
            ->method('getUser');
        $this->user->expects($this->never())
            ->method('getId');
        $this->commandBus->expects($this->never())
            ->method('dispatch');
        $this->avatarUrlResolver->expects($this->never())
            ->method('resolve');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->userMeAvatarProcessor->process(['avatarFile' => 'test'], $this->operation);
    }

    public function testProcessThrowsLogicExceptionWhenAvatarFileIsMissing(): void
    {
        $input = new UserMeAvatarInput();

        $this->security->expects($this->never())
            ->method('getUser');
        $this->user->expects($this->never())
            ->method('getId');
        $this->commandBus->expects($this->never())
            ->method('dispatch');
        $this->avatarUrlResolver->expects($this->never())
            ->method('resolve');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->userMeAvatarProcessor->process($input, $this->operation);
    }

    private function createValidUserMeAvatarInput(): UserMeAvatarInput
    {
        $input = new UserMeAvatarInput();
        /** @var UploadedFile&MockObject $mockFile */
        $mockFile = $this->createMock(UploadedFile::class);
        $mockFile->expects($this->once())
            ->method('getClientOriginalName')
            ->willReturn('avatar.jpg');
        $mockFile->expects($this->never())
            ->method('getClientOriginalExtension');
        $mockFile->expects($this->once())
            ->method('isValid')
            ->willReturn(true);
        $input->avatarFile = $mockFile;

        return $input;
    }

    private function createDomainUser(): DomainUser
    {
        return DomainUser::register(
            id: UserId::fromString(Uuid::uuid4()->toString()),
            username: Username::fromString('testuser'),
            email: EmailAddress::fromString('test@example.com'),
            password: new HashedPassword('hash'),
            preferences: Preferences::fromArray(['lang' => 'fr']),
            now: new DateTimeImmutable(),
        );
    }
}
