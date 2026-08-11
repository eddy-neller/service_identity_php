<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Unit\Service\Storage;

use App\Application\Shared\Port\FileInterface;
use App\Infrastructure\Adapter\Storage\LocalAvatarStorage;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Filesystem\Filesystem;

final class LocalAvatarStorageTest extends TestCase
{
    private Filesystem $filesystem;

    private string $directory;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir() . '/avatar-storage-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    public function testStoreCopiesFileAndDeleteRemovesIt(): void
    {
        $sourcePath = tempnam(sys_get_temp_dir(), 'avatar_');
        if (false === $sourcePath) {
            self::fail('Failed to create avatar source file.');
        }

        file_put_contents($sourcePath, 'avatar-content');
        $storage = new LocalAvatarStorage($this->parameterBag(), $this->filesystem, new NullLogger());
        $file = $this->createStub(FileInterface::class);
        $file->method('getPathname')->willReturn($sourcePath);
        $file->method('getMimeType')->willReturn('image/jpeg');

        try {
            $avatarName = $storage->store($file);
            $path = $this->directory . '/' . $avatarName;

            $this->assertMatchesRegularExpression('/^[a-z0-9]{32}\\.jpg$/', $avatarName);
            $this->assertFileExists($path);
            $this->assertSame('avatar-content', file_get_contents($path));

            $storage->delete($avatarName);

            $this->assertFileDoesNotExist($path);
        } finally {
            unlink($sourcePath);
        }
    }

    /**
     * Régression : `Filesystem::copy()` reporte les droits de la source sur la cible, et la
     * source est le fichier temporaire d'upload PHP, créé en 0600. L'avatar était donc écrit
     * en 0600 pour www-data, illisible par le worker nginx — qui répondait 403 sur l'image.
     * Les droits doivent être fixés explicitement, quels que soient ceux de la source.
     */
    public function testStoreMakesFileReadableWhateverTheSourcePermissions(): void
    {
        $sourcePath = tempnam(sys_get_temp_dir(), 'avatar_');
        if (false === $sourcePath) {
            self::fail('Failed to create avatar source file.');
        }

        file_put_contents($sourcePath, 'avatar-content');
        chmod($sourcePath, 0600);

        $storage = new LocalAvatarStorage($this->parameterBag(), $this->filesystem, new NullLogger());
        $file = $this->createStub(FileInterface::class);
        $file->method('getPathname')->willReturn($sourcePath);
        $file->method('getMimeType')->willReturn('image/png');

        try {
            $path = $this->directory . '/' . $storage->store($file);

            $this->assertSame('644', decoct(fileperms($path) & 0777));
        } finally {
            unlink($sourcePath);
        }
    }

    public function testStoreThrowsWhenSourceFileCannotBeCopied(): void
    {
        $storage = new LocalAvatarStorage($this->parameterBag(), $this->filesystem, new NullLogger());
        $file = $this->createStub(FileInterface::class);
        $file->method('getPathname')->willReturn('/path/that/does/not/exist.jpg');
        $file->method('getMimeType')->willReturn('image/jpeg');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to store avatar.');

        $storage->store($file);
    }

    public function testDeleteIgnoresInvalidFileName(): void
    {
        $storage = new LocalAvatarStorage($this->parameterBag(), $this->filesystem, new NullLogger());

        $storage->delete('invalid-avatar-name');

        $this->assertDirectoryDoesNotExist($this->directory);
    }

    private function parameterBag(): ParameterBag
    {
        return new ParameterBag([
            'app.avatar.uploadDirectory' => $this->directory,
        ]);
    }
}
