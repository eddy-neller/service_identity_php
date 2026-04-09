<?php

declare(strict_types=1);

namespace App\Presentation\User\Dto;

use App\Domain\User\Security\ValueObject\RoleSet;
use App\Domain\User\Security\ValueObject\UserStatus;
use App\Presentation\User\Validator as AppAssert;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class UserPostInput
{
    #[Assert\Sequentially([
        new Assert\NotBlank(),
        new Assert\Email(),
        new Assert\Length(
            min: 6,
            max: 100,
            minMessage: 'The email must be at least {{ limit }} characters long.',
            maxMessage: 'The email must be at most {{ limit }} characters long.'
        ),
        new AppAssert\EmailNotExists(),
    ])]
    #[Groups(groups: ['user:admin'])]
    public string $email;

    #[Assert\Sequentially([
        new Assert\NotBlank(),
        new Assert\Length(
            min: 2,
            max: 20,
            minMessage: 'The username must be at least {{ limit }} characters long.',
            maxMessage: 'The username must be at most {{ limit }} characters long.'
        ),
        new AppAssert\UsernameNotExists(),
    ])]
    #[Groups(groups: ['user:admin'])]
    public string $username;

    #[Assert\NotBlank]
    #[Assert\Regex(
        pattern: '/^(?=.*[()!@#$%^&*_-])(?=.*\d)(?=.*[A-Z]).{8,30}$/',
        message: 'Invalid password.'
    )]
    #[Groups(groups: ['user:admin'])]
    public string $password;

    #[Assert\Length(
        min: 2,
        max: 50,
        minMessage: 'The firstname must be at least {{ limit }} characters long.',
        maxMessage: 'The firstname must be at most {{ limit }} characters long.'
    )]
    #[Groups(groups: ['user:admin'])]
    public ?string $firstname = null;

    #[Assert\Length(
        min: 2,
        max: 50,
        minMessage: 'The lastname must be at least {{ limit }} characters long.',
        maxMessage: 'The lastname must be at most {{ limit }} characters long.'
    )]
    #[Groups(groups: ['user:admin'])]
    public ?string $lastname = null;

    #[Assert\NotBlank]
    #[Assert\Choice(
        choices: [
            RoleSet::ROLE_USER,
            RoleSet::ROLE_MODERATEUR,
            RoleSet::ROLE_ADMIN,
        ],
        multiple: true,
    )]
    #[Groups(groups: ['user:admin'])]
    public array $roles;

    #[Assert\NotBlank]
    #[Assert\Choice(
        choices: [
            UserStatus::INACTIVE,
            UserStatus::ACTIVE,
            UserStatus::BLOCKED,
        ],
        message: 'Invalid status.'
    )]
    #[Groups(groups: ['user:admin'])]
    public int $status;
}
