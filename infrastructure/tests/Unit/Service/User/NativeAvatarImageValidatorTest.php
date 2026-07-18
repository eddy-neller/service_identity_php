<?php

declare(strict_types=1);

namespace App\Infrastructure\Tests\Unit\Service\User;

use App\Application\Shared\Port\FileInterface;
use App\Domain\User\Exception\Profile\InvalidAvatarException;
use App\Infrastructure\Service\User\NativeAvatarImageValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

final class NativeAvatarImageValidatorTest extends TestCase
{
    /** @var list<string> */
    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testValidateAcceptsValidPng(): void
    {
        $path = $this->createTempFile(
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/wcAAwAB/8z9R5UAAAAASUVORK5CYII='),
        );
        $file = $this->createFile($path, 'image/png');

        $this->createValidator(100, 1)->validate($file);

        $this->addToAssertionCount(1);
    }

    public function testValidateThrowsWhenUploadIsInvalid(): void
    {
        $file = $this->createStub(FileInterface::class);
        $file->method('isValid')->willReturn(false);

        $this->expectException(InvalidAvatarException::class);
        $this->expectExceptionMessage('No avatar file provided.');

        $this->createValidator(100, 512)->validate($file);
    }

    public function testValidateThrowsWhenFileIsEmpty(): void
    {
        $path = $this->createTempFile('');
        $file = $this->createFile($path, 'image/png', 0);

        $this->expectException(InvalidAvatarException::class);
        $this->expectExceptionMessage('No avatar file provided.');

        $this->createValidator(100, 512)->validate($file);
    }

    public function testValidateThrowsWhenMimeTypeIsNotAllowed(): void
    {
        $path = $this->createTempFile('not-an-image');
        $file = $this->createFile($path, 'text/plain');

        $this->expectException(InvalidAvatarException::class);
        $this->expectExceptionMessage('Invalid avatar file type: text/plain.');

        $this->createValidator(100, 512)->validate($file);
    }

    public function testValidateThrowsWhenFileExceedsMaximumSize(): void
    {
        $path = $this->createValidPng();
        $file = $this->createFile($path, 'image/png', 101);

        $this->expectException(InvalidAvatarException::class);
        $this->expectExceptionMessage('Avatar file exceeds the maximum allowed size (100 bytes).');

        $this->createValidator(100, 512)->validate($file);
    }

    public function testValidateThrowsWhenImageCannotBeRead(): void
    {
        $path = $this->createTempFile('not-an-image');
        $file = $this->createFile($path, 'image/png');

        $this->expectException(InvalidAvatarException::class);
        $this->expectExceptionMessage('Avatar dimensions exceed the maximum allowed (512x512).');

        $this->createValidator(100, 512)->validate($file);
    }

    public function testValidateThrowsWhenMimeTypeDoesNotMatchImageContent(): void
    {
        $path = $this->createValidPng();
        $file = $this->createFile($path, 'image/jpeg');

        $this->expectException(InvalidAvatarException::class);
        $this->expectExceptionMessage('Invalid avatar file type: image/jpeg.');

        $this->createValidator(100, 512)->validate($file);
    }

    public function testValidateThrowsWhenImageExceedsMaximumDimension(): void
    {
        $path = $this->createValidPng();
        $file = $this->createFile($path, 'image/png');

        $this->expectException(InvalidAvatarException::class);
        $this->expectExceptionMessage('Avatar dimensions exceed the maximum allowed (0x0).');

        $this->createValidator(100, 0)->validate($file);
    }

    private function createValidPng(): string
    {
        return $this->createTempFile(
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/wcAAwAB/8z9R5UAAAAASUVORK5CYII='),
        );
    }

    private function createTempFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'avatar-validator-');
        if (false === $path) {
            self::fail('Unable to create temporary avatar file.');
        }

        file_put_contents($path, $contents);
        $this->paths[] = $path;

        return $path;
    }

    private function createFile(string $path, string $mimeType, ?int $size = null): FileInterface
    {
        $file = $this->createStub(FileInterface::class);
        $file->method('isValid')->willReturn(true);
        $file->method('getPathname')->willReturn($path);
        $file->method('getMimeType')->willReturn($mimeType);
        $file->method('getSize')->willReturn($size ?? filesize($path));

        return $file;
    }

    private function createValidator(int $maxSize, int $maxDimension): NativeAvatarImageValidator
    {
        return new NativeAvatarImageValidator(new ParameterBag([
            'app.avatar.max_size' => $maxSize,
            'app.avatar.max_dimension' => $maxDimension,
        ]));
    }
}
