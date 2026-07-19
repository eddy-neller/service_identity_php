<?php

declare(strict_types=1);

namespace App\Infrastructure\Service\Storage;

use App\Application\Shared\Port\FileInterface;
use App\Application\User\Port\AvatarStorageInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;

final readonly class LocalAvatarStorage implements AvatarStorageInterface
{
    public function __construct(
        private ParameterBagInterface $parameterBag,
        private Filesystem $filesystem,
        private LoggerInterface $logger,
    ) {
    }

    public function store(FileInterface $file): string
    {
        $fileName = $this->generateFileName($file);

        try {
            $this->filesystem->mkdir($this->uploadDirectory(), 0755);
            $this->filesystem->copy($file->getPathname(), $this->pathFor($fileName), true);
        } catch (IOExceptionInterface $exception) {
            throw new RuntimeException('Unable to store avatar.', previous: $exception);
        }

        return $fileName;
    }

    public function delete(string $fileName): void
    {
        if (1 !== preg_match('/^[a-z0-9]{32}\.[a-z0-9]{1,10}$/D', $fileName)) {
            $this->logger->warning('Unable to delete avatar with an invalid file name.', [
                'avatar' => $fileName,
            ]);

            return;
        }

        try {
            $this->filesystem->remove($this->pathFor($fileName));
        } catch (IOExceptionInterface $exception) {
            $this->logger->warning('Unable to delete avatar file.', [
                'avatar' => $fileName,
                'exception' => $exception,
            ]);
        }
    }

    private function generateFileName(FileInterface $file): string
    {
        $extension = match ($file->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'bin',
        };

        return bin2hex(random_bytes(16)) . '.' . $extension;
    }

    private function pathFor(string $fileName): string
    {
        if (1 !== preg_match('/^[a-z0-9]{32}\.[a-z0-9]{1,10}$/D', $fileName)) {
            throw new RuntimeException('Invalid avatar file name.');
        }

        return rtrim($this->uploadDirectory(), '/') . '/' . $fileName;
    }

    private function uploadDirectory(): string
    {
        return (string) $this->parameterBag->get('app.avatar.uploadDirectory');
    }
}
