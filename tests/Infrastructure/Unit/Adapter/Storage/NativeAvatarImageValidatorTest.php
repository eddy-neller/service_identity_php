<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Unit\Adapter\Storage;

use App\Application\Shared\Port\FileInterface;
use App\Domain\User\Exception\Profile\InvalidAvatarException;
use App\Infrastructure\Adapter\Storage\NativeAvatarImageValidator;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public static function provideAcceptedDimensions(): Generator
    {
        yield 'At the minimum' => [2, 2];
        yield 'At the maximum' => [3, 3];
        yield 'Minimum width, maximum height' => [2, 3];
    }

    #[DataProvider('provideAcceptedDimensions')]
    public function testValidateAcceptsDimensionsWithinBounds(int $width, int $height): void
    {
        $file = $this->createFile($this->createPng($width, $height), 'image/png');

        $this->createValidator(1000, minDimension: 2, maxDimension: 3)->validate($file);

        $this->addToAssertionCount(1);
    }

    public function testValidateThrowsWhenUploadIsInvalid(): void
    {
        $file = $this->createStub(FileInterface::class);
        $file->method('isValid')->willReturn(false);

        $this->expectException(InvalidAvatarException::class);
        $this->expectExceptionMessage('No avatar file provided.');

        $this->createValidator(100)->validate($file);
    }

    public function testValidateThrowsWhenFileIsEmpty(): void
    {
        $path = $this->createTempFile('');
        $file = $this->createFile($path, 'image/png', 0);

        $this->expectException(InvalidAvatarException::class);
        $this->expectExceptionMessage('No avatar file provided.');

        $this->createValidator(100)->validate($file);
    }

    public function testValidateThrowsWhenMimeTypeIsNotAllowed(): void
    {
        $path = $this->createTempFile('not-an-image');
        $file = $this->createFile($path, 'text/plain');

        $this->expectException(InvalidAvatarException::class);
        $this->expectExceptionMessage('Invalid avatar file type: text/plain.');

        $this->createValidator(100)->validate($file);
    }

    public function testValidateThrowsWhenFileExceedsMaximumSize(): void
    {
        $file = $this->createFile($this->createPng(1, 1), 'image/png', 101);

        $this->expectException(InvalidAvatarException::class);
        $this->expectExceptionMessage('Avatar file exceeds the maximum allowed size (100 bytes).');

        $this->createValidator(100)->validate($file);
    }

    public function testValidateThrowsWhenImageCannotBeRead(): void
    {
        $path = $this->createTempFile('not-an-image');
        $file = $this->createFile($path, 'image/png');

        $this->expectException(InvalidAvatarException::class);
        $this->expectExceptionMessage('Avatar file is not a readable image.');

        $this->createValidator(100)->validate($file);
    }

    public function testValidateThrowsWhenMimeTypeDoesNotMatchImageContent(): void
    {
        $file = $this->createFile($this->createPng(1, 1), 'image/jpeg');

        $this->expectException(InvalidAvatarException::class);
        $this->expectExceptionMessage('Invalid avatar file type: image/jpeg.');

        $this->createValidator(1000)->validate($file);
    }

    public static function provideRejectedDimensions(): Generator
    {
        yield 'Width below the minimum' => [1, 2];
        yield 'Height below the minimum' => [2, 1];
        yield 'Width above the maximum' => [4, 3];
        yield 'Height above the maximum' => [3, 4];
    }

    #[DataProvider('provideRejectedDimensions')]
    public function testValidateThrowsWhenDimensionsAreOutOfBounds(int $width, int $height): void
    {
        $file = $this->createFile($this->createPng($width, $height), 'image/png');

        $this->expectException(InvalidAvatarException::class);
        $this->expectExceptionMessage('Avatar dimensions must be between 2 and 3 pixels.');

        $this->createValidator(1000, minDimension: 2, maxDimension: 3)->validate($file);
    }

    /**
     * PNG niveaux de gris de la taille demandee, construit octet par octet : l'image `ci`
     * n'embarque pas GD, et `getimagesize()` n'a besoin que d'un en-tete valide.
     */
    private function createPng(int $width, int $height): string
    {
        $chunk = static fn (string $type, string $data): string => pack('N', strlen($data))
            . $type . $data . pack('N', crc32($type . $data));

        $rows = str_repeat("\x00" . str_repeat("\x80", $width), $height);

        return $this->createTempFile(
            "\x89PNG\r\n\x1a\n"
            . $chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 0, 0, 0, 0))
            . $chunk('IDAT', (string) gzcompress($rows))
            . $chunk('IEND', ''),
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

    private function createValidator(int $maxSize, int $minDimension = 1, int $maxDimension = 512): NativeAvatarImageValidator
    {
        return new NativeAvatarImageValidator(new ParameterBag([
            'app.avatar.max_size' => $maxSize,
            'app.avatar.min_dimension' => $minDimension,
            'app.avatar.max_dimension' => $maxDimension,
        ]));
    }
}
