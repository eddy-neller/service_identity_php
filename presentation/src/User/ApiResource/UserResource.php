<?php

declare(strict_types=1);

namespace App\Presentation\User\ApiResource;

use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use ApiPlatform\OpenApi\Model\RequestBody;
use App\Domain\User\ValueObject\Security\RoleSet;
use App\Infrastructure\Entity\User\User;
use App\Presentation\RouteRequirements;
use App\Presentation\User\Dto\Me\UserMeAvatarInput;
use App\Presentation\User\Dto\Me\UserMePasswordUpdateInput;
use App\Presentation\User\Dto\PasswordResetCheckInput;
use App\Presentation\User\Dto\PasswordResetConfirmInput;
use App\Presentation\User\Dto\PasswordResetRequestInput;
use App\Presentation\User\Dto\UserActivationRequestInput;
use App\Presentation\User\Dto\UserActivationValidationInput;
use App\Presentation\User\Dto\UserAvatarInput;
use App\Presentation\User\Dto\UserPatchInput;
use App\Presentation\User\Dto\UserPostInput;
use App\Presentation\User\Dto\UserRegisterInput;
use App\Presentation\User\State\Me\UserMeAvatarProcessor;
use App\Presentation\User\State\Me\UserMePasswordUpdateProcessor;
use App\Presentation\User\State\Me\UserMeProvider;
use App\Presentation\User\State\PasswordResetCheckProcessor;
use App\Presentation\User\State\PasswordResetConfirmProcessor;
use App\Presentation\User\State\PasswordResetRequestProcessor;
use App\Presentation\User\State\UserActivationRequestProcessor;
use App\Presentation\User\State\UserActivationValidationProcessor;
use App\Presentation\User\State\UserAdminCollectionProvider;
use App\Presentation\User\State\UserAvatarProcessor;
use App\Presentation\User\State\UserDeleteProcessor;
use App\Presentation\User\State\UserGetProvider;
use App\Presentation\User\State\UserPatchProcessor;
use App\Presentation\User\State\UserPostProcessor;
use App\Presentation\User\State\UserRegisterProcessor;
use ArrayObject;
use DateTimeImmutable;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    shortName: 'User',
    operations: [
        new Get(
            uriTemplate: '/users/{id}',
            requirements: ['id' => RouteRequirements::UUID],
            openapi: new Model\Operation(
                summary: 'Display a user (Admin only).',
                description: 'Display a user. This endpoint is accessible only by administrators.',
                security: [['JWT' => []]]
            ),
            security: "is_granted('" . RoleSet::ROLE_ADMIN . "')",
            name: self::PREFIX_NAME . 'get',
            provider: UserGetProvider::class,
        ),
        new Get(
            uriTemplate: '/users/me',
            openapi: new Model\Operation(
                security: [['JWT' => []]]
            ),
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            name: self::PREFIX_NAME . 'me',
            provider: UserMeProvider::class,
        ),
        new Patch(
            uriTemplate: '/users/{id}',
            requirements: ['id' => RouteRequirements::UUID],
            openapi: new Model\Operation(
                summary: 'Update a user (Admin only).',
                description: 'Update a user. All fields are optional. This endpoint is accessible only by administrators.',
                security: [['JWT' => []]]
            ),
            security: "is_granted('" . RoleSet::ROLE_ADMIN . "')",
            input: UserPatchInput::class,
            name: self::PREFIX_NAME . 'patch',
            processor: UserPatchProcessor::class,
        ),
        new Delete(
            uriTemplate: '/users/{id}',
            requirements: ['id' => RouteRequirements::UUID],
            status: 204,
            openapi: new Model\Operation(
                summary: 'Delete a user (Admin only).',
                description: 'Delete a user. This endpoint is accessible only by administrators.',
                security: [['JWT' => []]]
            ),
            security: "is_granted('" . RoleSet::ROLE_ADMIN . "')",
            name: self::PREFIX_NAME . 'delete',
            processor: UserDeleteProcessor::class,
        ),
        new Post(
            uriTemplate: '/users/me/avatar',
            inputFormats: ['multipart' => ['multipart/form-data']],
            openapi: new Model\Operation(
                summary: 'Update my avatar image',
                requestBody: new RequestBody(
                    content: new ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'avatarFile' => [
                                        'type' => 'string',
                                        'format' => 'binary',
                                        'description' => 'Avatar image (JPEG, PNG or WebP; max 2 MiB, max 512×512px)',
                                    ],
                                ],
                            ],
                        ],
                    ]),
                ),
                security: [['JWT' => []]]
            ),
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            input: UserMeAvatarInput::class,
            name: self::PREFIX_NAME . 'me-avatar',
            processor: UserMeAvatarProcessor::class,
        ),
        new Post(
            uriTemplate: '/users/{id}/avatar',
            inputFormats: ['multipart' => ['multipart/form-data']],
            requirements: ['id' => RouteRequirements::UUID],
            openapi: new Model\Operation(
                summary: 'Upload avatar for a user (admin only)',
                requestBody: new RequestBody(
                    content: new ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'avatarFile' => [
                                        'type' => 'string',
                                        'format' => 'binary',
                                        'description' => 'Avatar image (JPEG, PNG or WebP; max 2 MiB, max 512×512px)',
                                    ],
                                ],
                            ],
                        ],
                    ]),
                ),
                security: [['JWT' => []]]
            ),
            security: "is_granted('" . RoleSet::ROLE_ADMIN . "')",
            input: UserAvatarInput::class,
            name: self::PREFIX_NAME . 'avatar',
            processor: UserAvatarProcessor::class,
        ),
        new Patch(
            uriTemplate: '/users/me/update-password',
            status: 204,
            openapi: new Model\Operation(
                summary: 'Update my password.',
                description: 'Update my password.',
                requestBody: new RequestBody(
                    description: 'Update my password request body',
                    required: true
                ),
                security: [['JWT' => []]],
            ),
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            input: UserMePasswordUpdateInput::class,
            output: false,
            name: self::PREFIX_NAME . 'me-update-password',
            processor: UserMePasswordUpdateProcessor::class,
        ),
        new GetCollection(
            uriTemplate: '/users',
            openapi: new Model\Operation(
                summary: 'Get all users (Admin only).',
                description: 'Get all users. This endpoint is accessible only by administrators.',
                security: [['JWT' => []]],
            ),
            paginationClientItemsPerPage: true,
            security: "is_granted('" . RoleSet::ROLE_ADMIN . "')",
            name: self::PREFIX_NAME . 'admin-col',
            provider: UserAdminCollectionProvider::class,
        ),
        new Post(
            uriTemplate: '/users',
            openapi: new Model\Operation(
                summary: 'Create a new user (Admin only).',
                description: 'Create a new user. This endpoint is accessible only by administrators.',
                requestBody: new RequestBody(
                    description: 'User creation request body',
                    required: true
                ),
                security: [['JWT' => []]]
            ),
            security: "is_granted('" . RoleSet::ROLE_ADMIN . "')",
            input: UserPostInput::class,
            name: self::PREFIX_NAME . 'admin-create',
            processor: UserPostProcessor::class,
        ),
        new Post(
            uriTemplate: '/users/register',
            openapi: new Model\Operation(
                summary: 'Register a new user.',
                description: 'Register a new user account.',
                requestBody: new RequestBody(
                    description: 'User registration request body',
                    required: true
                ),
            ),
            input: UserRegisterInput::class,
            name: self::PREFIX_NAME . 'register',
            processor: UserRegisterProcessor::class,
        ),
        new Post(
            uriTemplate: '/users/register/email-activation-request',
            status: 204,
            openapi: new Model\Operation(
                summary: 'Allows you to request an activation email.',
                description: 'Sends an activation email to the user with a token to activate their account.',
                requestBody: new RequestBody(
                    description: 'Email activation request request body',
                    required: true
                ),
            ),
            input: UserActivationRequestInput::class,
            output: false,
            name: self::PREFIX_NAME . 'register-resend',
            processor: UserActivationRequestProcessor::class,
        ),
        new Post(
            uriTemplate: '/users/register/validation',
            status: 204,
            openapi: new Model\Operation(
                summary: 'Validates the registration of the account created.',
                description: 'Validates an email-based user registration using the provided token.',
                requestBody: new RequestBody(
                    description: 'Register validation request body',
                    required: true
                ),
            ),
            input: UserActivationValidationInput::class,
            output: false,
            name: self::PREFIX_NAME . 'register-validation',
            processor: UserActivationValidationProcessor::class,
        ),
        new Post(
            uriTemplate: '/users/reset-password/request',
            status: 204,
            openapi: new Model\Operation(
                summary: 'Request a password reset email.',
                description: 'Sends an activation email to the user with a token to change the password.',
                requestBody: new RequestBody(
                    description: 'Password reset request request body',
                    required: true
                ),
            ),
            input: PasswordResetRequestInput::class,
            output: false,
            name: self::PREFIX_NAME . 'password-reset-request',
            processor: PasswordResetRequestProcessor::class,
        ),
        new Post(
            uriTemplate: '/users/reset-password/check',
            status: 204,
            openapi: new Model\Operation(
                summary: 'Check a password reset token.',
                description: 'Check a password reset token.',
                requestBody: new RequestBody(
                    description: 'Password reset check request body',
                    required: true
                ),
            ),
            input: PasswordResetCheckInput::class,
            output: false,
            name: self::PREFIX_NAME . 'password-reset-check',
            processor: PasswordResetCheckProcessor::class,
        ),
        new Post(
            uriTemplate: '/users/reset-password/confirm',
            status: 204,
            openapi: new Model\Operation(
                summary: 'Confirm password reset with a new password.',
                description: 'Confirm password reset with a new password.',
                requestBody: new RequestBody(
                    description: 'Password reset confirm request body',
                    required: true
                ),
            ),
            input: PasswordResetConfirmInput::class,
            output: false,
            name: self::PREFIX_NAME . 'password-reset-confirm',
            processor: PasswordResetConfirmProcessor::class,
        ),
    ],
    order: ['createdAt' => 'DESC'],
    stateOptions: new Options(entityClass: User::class),
)]
final class UserResource
{
    private const string PREFIX_NAME = 'users-';

    #[Groups(['user:read'])]
    public string $id;

    #[Groups(['user:read', 'user:admin'])]
    public ?string $firstname = null;

    #[Groups(['user:read', 'user:admin'])]
    public ?string $lastname = null;

    #[Groups(['user:read', 'user:admin'])]
    public string $username;

    #[Groups(['user:read', 'user:admin'])]
    public string $email;

    #[Groups(['user:read', 'user:admin'])]
    public array $roles = [];

    #[Groups(['user:read', 'user:admin'])]
    public int $status;

    #[Groups(['user:read'])]
    public ?string $avatarUrl = null;

    #[Groups(['user:read'])]
    public DateTimeImmutable $lastVisit;

    #[Groups(['user:item:read'])]
    public int $nbLogin = 0;

    #[Groups(['user:read'])]
    public DateTimeImmutable $createdAt;

    #[Groups(['user:read'])]
    public DateTimeImmutable $updatedAt;
}
