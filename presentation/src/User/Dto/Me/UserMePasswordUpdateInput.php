<?php

declare(strict_types=1);

namespace App\Presentation\User\Dto\Me;

use App\Presentation\User\Validator as AppAssert;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class UserMePasswordUpdateInput
{
    #[Assert\Sequentially([
        new Assert\NotBlank(),
        new AppAssert\CurrentPassword(),
    ])]
    #[Groups(groups: ['user:item:write'])]
    public ?string $currentPassword = null;

    #[Assert\NotBlank]
    #[Assert\Regex(
        pattern: '/^(?=.*[()!@#$%^&*_-])(?=.*\d)(?=.*[A-Z]).{8,30}$/',
        message: 'Invalid new password.'
    )]
    #[Assert\Expression(
        'this.newPassword != this.currentPassword',
        message: 'The new password must be different from the current password.'
    )]
    #[Groups(groups: ['user:item:write'])]
    public ?string $newPassword = null;

    #[Assert\NotBlank]
    #[Assert\EqualTo(
        propertyPath: 'newPassword',
        message: 'The password confirmation does not match.'
    )]
    #[Groups(groups: ['user:item:write'])]
    public ?string $confirmNewPassword = null;
}
