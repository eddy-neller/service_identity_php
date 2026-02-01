<?php

declare(strict_types=1);

namespace App\Infrastructure\Tests\Unit\Command\User;

use App\Infrastructure\Command\User\GenerateUserImagesCommand;
use App\Infrastructure\Entity\User\User;
use App\Infrastructure\Persistence\Doctrine\User\UserRepository;
use App\Infrastructure\Service\Media\CustomImageProvider;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class GenerateUserImagesCommandTest extends KernelTestCase
{
    public function testNoUsersInDatabase(): void
    {
        $tester = $this->getCommandTesterWithNoUsers();
        $tester->execute([]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Aucun utilisateur à traiter.', $output);
        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testNoUsersWithoutImages(): void
    {
        $tester = $this->getCommandTesterForScenario(true);
        $tester->execute([]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('0 utilisateur(s) mis à jour.', $output);
        $this->assertStringContainsString('Traitement de 1 utilisateur(s)...', $output);
    }

    public function testUsersWithoutImagesUpdated(): void
    {
        $tester = $this->getCommandTesterForScenario(false);
        $tester->execute([]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('1 utilisateur(s) mis à jour.', $output);
    }

    public function testMixedUsersScenario(): void
    {
        $tester = $this->getCommandTesterForMixedScenario();
        $tester->execute([]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('1 utilisateur(s) mis à jour.', $output);
        $this->assertStringContainsString('Traitement de 2 utilisateur(s)...', $output);
    }

    public function testImageGenerationFailure(): void
    {
        $tester = $this->getCommandTesterForScenario(false, true);
        $tester->execute([]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Échec de génération des images pour l\'utilisateur', $output);
        $this->assertStringContainsString('0 utilisateur(s) mis à jour.', $output);
    }

    private function getCommandTesterWithNoUsers(): CommandTester
    {
        /** @var UserRepository&MockObject $userRepo */
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        /** @var EntityManagerInterface&MockObject $em */
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        /** @var CustomImageProvider&MockObject $customImageProvider */
        $customImageProvider = $this->createMock(CustomImageProvider::class);
        $customImageProvider->expects($this->never())
            ->method('customImage');

        $command = new GenerateUserImagesCommand($userRepo, $em, $customImageProvider);

        $application = new Application(self::bootKernel());
        $application->addCommand($command);

        return new CommandTester($command);
    }

    private function getCommandTesterForScenario(bool $hasAvatar, bool $imageGenerationFails = false): CommandTester
    {
        // Mock User
        $user = $this->createMock(User::class);
        $user->method('getFullname')->willReturn('Test User');
        $user->method('getAvatarName')->willReturn($hasAvatar ? 'avatar.jpg' : null);
        $user->method('getId')->willReturn(Uuid::uuid4());

        // Set expectations based on scenario
        if (!$hasAvatar && !$imageGenerationFails) {
            $user->expects($this->once())->method('setAvatarName')->with('test_avatar.jpg');
        } else {
            $user->expects($this->never())->method('setAvatarName');
        }

        // Mock User Repository
        /** @var UserRepository&MockObject $userRepo */
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->expects($this->once())
            ->method('findAll')
            ->willReturn([$user]);

        /** @var EntityManagerInterface&MockObject $em */
        $em = $this->createMock(EntityManagerInterface::class);

        // Flush uniquement si un utilisateur a été mis à jour
        if (!$hasAvatar && !$imageGenerationFails) {
            $em->expects($this->once())->method('flush');
        } else {
            $em->expects($this->never())->method('flush');
        }

        // Mock CustomImageProvider
        /** @var CustomImageProvider&MockObject $customImageProvider */
        $customImageProvider = $this->createMock(CustomImageProvider::class);

        if ($hasAvatar) {
            $customImageProvider->expects($this->never())
                ->method('customImage');
        } elseif ($imageGenerationFails) {
            $customImageProvider->expects($this->once())
                ->method('customImage')
                ->willReturn(null);
        } else {
            $customImageProvider->expects($this->once())
                ->method('customImage')
                ->with('public/uploads/images/user/avatar', 96, 96)
                ->willReturn('test_avatar.jpg');
        }

        $command = new GenerateUserImagesCommand($userRepo, $em, $customImageProvider);

        $application = new Application(self::bootKernel());
        $application->addCommand($command);

        return new CommandTester($command);
    }

    private function getCommandTesterForMixedScenario(): CommandTester
    {
        // Utilisateur avec avatar
        $userWithAvatar = $this->createMock(User::class);
        $userWithAvatar->method('getFullname')->willReturn('User With Avatar');
        $userWithAvatar->method('getAvatarName')->willReturn('existing_avatar.jpg');
        $userWithAvatar->method('getId')->willReturn(Uuid::uuid4());
        $userWithAvatar->expects($this->never())->method('setAvatarName');

        // Utilisateur sans avatar
        $userWithoutAvatar = $this->createMock(User::class);
        $userWithoutAvatar->method('getFullname')->willReturn('User Without Avatar');
        $userWithoutAvatar->method('getAvatarName')->willReturn(null);
        $userWithoutAvatar->method('getId')->willReturn(Uuid::uuid4());
        $userWithoutAvatar->expects($this->once())->method('setAvatarName')->with('test_avatar.jpg');

        /** @var UserRepository&MockObject $userRepo */
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->expects($this->once())
            ->method('findAll')
            ->willReturn([$userWithAvatar, $userWithoutAvatar]);

        /** @var EntityManagerInterface&MockObject $em */
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        /** @var CustomImageProvider&MockObject $customImageProvider */
        $customImageProvider = $this->createMock(CustomImageProvider::class);
        $customImageProvider->expects($this->once())
            ->method('customImage')
            ->with('public/uploads/images/user/avatar', 96, 96)
            ->willReturn('test_avatar.jpg');

        $command = new GenerateUserImagesCommand($userRepo, $em, $customImageProvider);

        $application = new Application(self::bootKernel());
        $application->addCommand($command);

        return new CommandTester($command);
    }
}
