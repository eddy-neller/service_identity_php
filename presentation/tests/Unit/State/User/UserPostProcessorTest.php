<?php

declare(strict_types=1);

namespace App\Presentation\Tests\Unit\State\User;

use ApiPlatform\Metadata\Operation;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\User\Port\AvatarUrlResolverInterface;
use App\Application\User\UseCase\Command\CreateUserByAdmin\CreateUserByAdminCommand;
use App\Application\User\UseCase\Command\CreateUserByAdmin\CreateUserByAdminOutput;
use App\Domain\User\Identity\ValueObject\EmailAddress;
use App\Domain\User\Identity\ValueObject\UserId;
use App\Domain\User\Identity\ValueObject\Username;
use App\Domain\User\Model\User as DomainUser;
use App\Domain\User\Preference\ValueObject\Preferences;
use App\Domain\User\Security\ValueObject\HashedPassword;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\User\ApiResource\UserResource;
use App\Presentation\User\Dto\UserPostInput;
use App\Presentation\User\Presenter\UserResourcePresenter;
use App\Presentation\User\State\UserPostProcessor;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use Ramsey\Uuid\Uuid;
use stdClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class UserPostProcessorTest extends KernelTestCase
{
    private CommandBusInterface&MockObject $commandBus;

    private AvatarUrlResolverInterface&MockObject $avatarUrlResolver;

    private Operation&MockObject $operation;

    private UserPostProcessor $userPostProcessor;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(CommandBusInterface::class);
        $this->avatarUrlResolver = $this->createMock(AvatarUrlResolverInterface::class);
        $userResourcePresenter = new UserResourcePresenter($this->avatarUrlResolver);
        $this->operation = $this->createMock(Operation::class);
        $this->operation->expects($this->never())
            ->method('getName');

        $this->userPostProcessor = new UserPostProcessor(
            $this->commandBus,
            $userResourcePresenter,
        );
    }

    public function testProcessWithValidInput(): void
    {
        $input = $this->createValidUserPostInput();
        $domainUser = $this->createDomainUser();
        $output = new CreateUserByAdminOutput($domainUser);

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($command) use ($input, $output): CreateUserByAdminOutput {
                $this->assertInstanceOf(CreateUserByAdminCommand::class, $command);
                $this->assertSame($input->email, $command->email);
                $this->assertSame($input->username, $command->username);
                $this->assertSame($input->password, $command->plainPassword);
                $this->assertSame($input->roles, $command->roles);
                $this->assertSame($input->status, $command->status);

                return $output;
            });

        $this->avatarUrlResolver->expects($this->once())
            ->method('resolve')
            ->willReturn('/uploads/avatar.jpg');

        $result = $this->userPostProcessor->process($input, $this->operation);

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

        $this->userPostProcessor->process($invalidInput, $this->operation);
    }

    public function testProcessThrowsLogicExceptionForNullInput(): void
    {
        $this->commandBus->expects($this->never())
            ->method('dispatch');
        $this->avatarUrlResolver->expects($this->never())
            ->method('resolve');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->userPostProcessor->process(null, $this->operation);
    }

    public function testProcessThrowsLogicExceptionForStringInput(): void
    {
        $this->commandBus->expects($this->never())
            ->method('dispatch');
        $this->avatarUrlResolver->expects($this->never())
            ->method('resolve');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->userPostProcessor->process('invalid', $this->operation);
    }

    public function testProcessReturnsCreatedUser(): void
    {
        $input = $this->createValidUserPostInput();
        $domainUser = $this->createDomainUser();
        $output = new CreateUserByAdminOutput($domainUser);

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturn($output);

        $this->avatarUrlResolver->expects($this->once())
            ->method('resolve')
            ->willReturn('/uploads/avatar.jpg');

        $result = $this->userPostProcessor->process($input, $this->operation);

        $this->assertInstanceOf(UserResource::class, $result);
    }

    private function createValidUserPostInput(): UserPostInput
    {
        $input = new UserPostInput();
        $input->email = 'admin-created@example.com';
        $input->username = 'admincreated';
        $input->password = 'SecurePassword123!';
        $input->roles = ['ROLE_USER'];
        $input->status = 1;

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
