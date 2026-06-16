<?php

declare(strict_types=1);

namespace App\Presentation\User\Dto;

use App\Presentation\User\Dto\Partial\UserPreferences;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class UserRegisterInput
{
    #[Assert\Sequentially([
        new Assert\NotBlank(),
        new Assert\Email(),
        new Assert\Length(
            min: 4,
            max: 100,
            minMessage: 'The email must be at least {{ limit }} characters long.',
            maxMessage: 'The email must be at most {{ limit }} characters long.'
        ),
    ])]
    #[Groups(groups: ['user:item:write'])]
    public string $email;

    #[Assert\Sequentially([
        new Assert\NotBlank(),
        new Assert\Length(
            min: 2,
            max: 20,
            minMessage: 'The username must be at least {{ limit }} characters long.',
            maxMessage: 'The username must be at most {{ limit }} characters long.'
        ),
    ])]
    #[Groups(groups: ['user:item:write'])]
    public string $username;

    #[Assert\NotBlank]
    #[Assert\Regex(
        pattern: '/^(?=.*[()!@#$%^&*_-])(?=.*\d)(?=.*[A-Z]).{8,30}$/',
        message: 'Invalid password.'
    )]
    #[Groups(groups: ['user:item:write'])]
    public string $password;

    #[Assert\NotBlank]
    #[Assert\EqualTo(
        propertyPath: 'password',
        message: 'The password confirmation does not match.'
    )]
    #[Groups(groups: ['user:item:write'])]
    public string $confirmPassword;

    #[Assert\Valid]
    #[Assert\NotBlank]
    #[Groups(groups: ['user:item:write'])]
    public UserPreferences $preferences;

    /* The following attributes exist only for a validation purpose. They will not be serialized. */
}
