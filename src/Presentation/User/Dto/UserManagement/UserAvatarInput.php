<?php

declare(strict_types=1);

namespace App\Presentation\User\Dto\UserManagement;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class UserAvatarInput
{
    #[Groups(['user_management:write'])]
    #[Assert\NotNull(message: 'Please upload an avatar.')]
    #[Assert\File(
        maxSize: '3M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp']
    )]
    public ?UploadedFile $avatarFile = null;
}
