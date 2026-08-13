<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter\Storage;

use App\Application\Shared\Port\FileInterface;
use App\Application\User\Port\AvatarImageValidatorInterface;
use App\Domain\User\Exception\Profile\InvalidAvatarException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final readonly class NativeAvatarImageValidator implements AvatarImageValidatorInterface
{
    private const array MIME_TYPE_ALIASES = [
        'image/jpeg' => 'image/jpeg',
        'image/pjpeg' => 'image/jpeg',
        'image/png' => 'image/png',
        'image/webp' => 'image/webp',
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

        $declaredMimeType = $file->getMimeType();
        $declaredMimeAlias = self::MIME_TYPE_ALIASES[$declaredMimeType] ?? null;
        if (null === $declaredMimeAlias) {
            throw InvalidAvatarException::invalidMimeType($declaredMimeType);
        }

        if ($file->getSize() > $maxSize) {
            throw InvalidAvatarException::tooLarge($maxSize);
        }

        $dimensions = getimagesize($file->getPathname());
        if (false === $dimensions) {
            throw InvalidAvatarException::invalidDimensions($maxDimension);
        }

        $contentMimeType = $dimensions['mime'];
        $contentMimeAlias = self::MIME_TYPE_ALIASES[$contentMimeType] ?? null;
        if (null === $contentMimeAlias || $contentMimeAlias !== $declaredMimeAlias) {
            throw InvalidAvatarException::invalidMimeType($declaredMimeType);
        }

        if ($dimensions[0] > $maxDimension || $dimensions[1] > $maxDimension) {
            throw InvalidAvatarException::invalidDimensions($maxDimension);
        }
    }
}
