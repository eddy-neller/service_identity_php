<?php

declare(strict_types=1);

namespace App\Infrastructure\Tests\Unit\Service\User;

use App\Application\Shared\Port\FileInterface;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\Identity\ValueObject\UserId;
use App\Infrastructure\Entity\User\User as DoctrineUser;
use App\Infrastructure\Service\User\AvatarUploader;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class AvatarUploaderTest extends KernelTestCase
{
    private EntityManagerInterface&MockObject $em;

    private AvatarUploader $uploader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->uploader = new AvatarUploader($this->em);
    }

    public function testUploadReturnsAvatar(): void
    {
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $tempFile = tempnam(sys_get_temp_dir(), 'avatar_');
        if (false === $tempFile) {
            self::fail('Failed to create temp file for upload test.');
        }

        file_put_contents($tempFile, 'avatar');

        $file = $this->createStub(FileInterface::class);
        $file->method('getPathname')->willReturn($tempFile);
        $file->method('getClientOriginalName')->willReturn('avatar.jpg');
        $file->method('getMimeType')->willReturn('image/jpeg');

        $entity = $this->createMock(DoctrineUser::class);
        $entity->expects($this->once())
            ->method('setAvatarFile')
            ->with($this->callback(static function (UploadedFile $uploadedFile) use ($tempFile): bool {
                return $uploadedFile->getPathname() === $tempFile;
            }));
        $entity->method('getAvatarName')->willReturn('stored-avatar.jpg');

        $this->em->expects($this->once())
            ->method('getReference')
            ->with(DoctrineUser::class, $userId->toString())
            ->willReturn($entity);

        $this->em->expects($this->once())
            ->method('flush');

        try {
            $avatar = $this->uploader->upload($userId, $file);

            $this->assertSame('stored-avatar.jpg', $avatar->fileName());
        } finally {
            @unlink($tempFile);
        }
    }

    public function testUploadThrowsWhenUserNotFound(): void
    {
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440001');
        $file = $this->createStub(FileInterface::class);

        $this->em->expects($this->once())
            ->method('getReference')
            ->with(DoctrineUser::class, $userId->toString())
            ->willThrowException(EntityNotFoundException::fromClassNameAndIdentifier(
                DoctrineUser::class,
                ['id' => $userId->toString()],
            ));

        $this->em->expects($this->never())
            ->method('flush');

        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage('User not found.');

        $this->uploader->upload($userId, $file);
    }
}
