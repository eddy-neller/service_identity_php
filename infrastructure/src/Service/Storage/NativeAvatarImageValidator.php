<?php

declare(strict_types=1);

namespace App\Infrastructure\Service\Storage;

use App\Application\Shared\Port\FileInterface;
use App\Application\User\Port\AvatarImageValidatorInterface;
use App\Domain\User\Exception\Profile\InvalidAvatarException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final readonly class NativeAvatarImageValidator implements AvatarImageValidatorInterface
{
    private const array ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function __construct(
        private ParameterBagInterface $parameterBag,
    ) {
    }

    public function validate(FileInterface $file): void
    {
        $maxSize = (int) $this->parameterBag->get('app.avatar.max_size');
        $maxDimension = (int) $this->parameterBag->get('app.avatar.max_dimension');

        if (!$file->isValid() || $file->getSize() <= 0) {
            throw InvalidAvatarException::missing();
        }

        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw InvalidAvatarException::invalidMimeType($mimeType);
        }

        if ($file->getSize() > $maxSize) {
            throw InvalidAvatarException::tooLarge($maxSize);
        }

        $dimensions = getimagesize($file->getPathname());
        if (false === $dimensions) {
            throw InvalidAvatarException::invalidDimensions($maxDimension);
        }

        if ($dimensions['mime'] !== $mimeType) {
            throw InvalidAvatarException::invalidMimeType($mimeType);
        }

        if ($dimensions[0] > $maxDimension || $dimensions[1] > $maxDimension) {
            throw InvalidAvatarException::invalidDimensions($maxDimension);
        }
    }
}
