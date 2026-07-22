<?php

declare(strict_types=1);

namespace App\Presentation\User\Dto\UserManagement;

use App\Domain\User\ValueObject\Security\RoleSet;
use App\Domain\User\ValueObject\Security\UserStatus;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class UserPatchInput
{
    #[Assert\Sequentially([
        new Assert\NotBlank(),
        new Assert\Email(),
    ])]
    #[Groups(groups: ['user_management:admin'])]
    public ?string $email = null;

    #[Assert\Sequentially([
        new Assert\Length(
            min: 2,
            max: 20,
            minMessage: 'The username must be at least {{ limit }} characters long.',
            maxMessage: 'The username must be at most {{ limit }} characters long.'
        ),
    ])]
    #[Groups(groups: ['user_management:admin'])]
    public ?string $username = null;

    #[Assert\Regex(
        pattern: '/^(?=.*[()!@#$%^&*_-])(?=.*\d)(?=.*[A-Z]).{8,30}$/',
        message: 'Invalid password.'
    )]
    #[Groups(groups: ['user_management:admin'])]
    public ?string $password = null;

    #[Assert\Length(
        min: 2,
        max: 50,
        minMessage: 'The firstname must be at least {{ limit }} characters long.',
        maxMessage: 'The firstname must be at most {{ limit }} characters long.'
    )]
    #[Groups(groups: ['user_management:admin'])]
    public ?string $firstname = null;

    #[Assert\Length(
        min: 2,
        max: 50,
        minMessage: 'The lastname must be at least {{ limit }} characters long.',
        maxMessage: 'The lastname must be at most {{ limit }} characters long.'
    )]
    #[Groups(groups: ['user_management:admin'])]
    public ?string $lastname = null;

    #[Assert\Choice(
        choices: [
            RoleSet::ROLE_USER,
            RoleSet::ROLE_MODERATEUR,
            RoleSet::ROLE_ADMIN,
        ],
        multiple: true,
        message: 'Invalid role.'
    )]
    #[Groups(groups: ['user_management:admin'])]
    public ?array $roles = null;

    #[Assert\Choice(
        choices: [
            UserStatus::INACTIVE,
            UserStatus::ACTIVE,
            UserStatus::BLOCKED,
        ],
        message: 'Invalid status.'
    )]
    #[Groups(groups: ['user_management:admin'])]
    public ?int $status = null;
}
