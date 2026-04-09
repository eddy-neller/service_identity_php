<?php

declare(strict_types=1);

namespace App\Infrastructure\Service\Media;

use Exception;
use Random\RandomException;
use RuntimeException;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @codeCoverageIgnore
 */
class CustomImageProvider
{
    public function customImage(string $dir, int $width = 0, int $height = 0): ?string
    {
        if ($width < 0 || $height < 0) {
            throw new RuntimeException('Les dimensions doivent être des entiers positifs.');
        }

        try {
            $width = $width > 0 ? $width : random_int(640, 1080);
            $height = $height > 0 ? $height : random_int(480, 960);
        } catch (RandomException) {
            throw new RuntimeException('Erreur lors de la génération des dimensions.');
        }

        $filesystem = new Filesystem();

        try {
            if (!$filesystem->exists($dir)) {
                $filesystem->mkdir($dir, 0755);
            }
        } catch (IOExceptionInterface) {
            throw new RuntimeException('Erreur lors de la création du répertoire des images.');
        }

        $filename = sprintf('%s.jpg', uniqid('', true));
        $filepath = rtrim($dir, '/') . '/' . $filename;

        $imageUrl = sprintf('https://picsum.photos/%d/%d', $width, $height);

        try {
            $imageContent = file_get_contents($imageUrl);

            if (false === $imageContent) {
                return null;
            }

            $filesystem->dumpFile($filepath, $imageContent);
        } catch (Exception) {
            return null;
        }

        return $filename;
    }
}
