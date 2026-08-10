<?php

declare(strict_types=1);

namespace App\Presentation\User\ApiResource\UserManagement;

use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use ApiPlatform\OpenApi\Model\RequestBody;
use App\Domain\User\ValueObject\Access\RoleSet;
use App\Infrastructure\Entity\User\User;
use App\Presentation\RouteRequirements;
use App\Presentation\User\ApiResource\UserResource;
use App\Presentation\User\Dto\UserManagement\UserAvatarInput;
use App\Presentation\User\Dto\UserManagement\UserPatchInput;
use App\Presentation\User\Dto\UserManagement\UserPostInput;
use App\Presentation\User\State\UserManagement\UserAdminCollectionProvider;
use App\Presentation\User\State\UserManagement\UserAvatarProcessor;
use App\Presentation\User\State\UserManagement\UserDeleteProcessor;
use App\Presentation\User\State\UserManagement\UserGetProvider;
use App\Presentation\User\State\UserManagement\UserPatchProcessor;
use App\Presentation\User\State\UserManagement\UserPostProcessor;
use ArrayObject;

#[ApiResource(
    shortName: 'UserManagement',
    operations: [
        new Get(
            uriTemplate: '/users/{id}',
            requirements: ['id' => RouteRequirements::UUID],
            openapi: new Model\Operation(
                summary: 'Display a user (Admin only).',
                description: 'Display a user. This endpoint is accessible only by administrators.',
                security: [['JWT' => []]]
            ),
            output: UserResource::class,
            name: self::PREFIX_NAME . '-get',
            provider: UserGetProvider::class,
        ),
        new Patch(
            uriTemplate: '/users/{id}',
            requirements: ['id' => RouteRequirements::UUID],
            openapi: new Model\Operation(
                summary: 'Update a user (Admin only).',
                description: 'Update a user. All fields are optional. This endpoint is accessible only by administrators.',
                security: [['JWT' => []]]
            ),
            input: UserPatchInput::class,
            output: UserResource::class,
            name: self::PREFIX_NAME . '-patch',
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
            name: self::PREFIX_NAME . '-delete',
            processor: UserDeleteProcessor::class,
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
            input: UserAvatarInput::class,
            output: UserResource::class,
            name: self::PREFIX_NAME . '-avatar',
            processor: UserAvatarProcessor::class,
        ),
        new GetCollection(
            uriTemplate: '/users',
            openapi: new Model\Operation(
                summary: 'Get all users (Admin only).',
                description: 'Get all users. This endpoint is accessible only by administrators.',
                security: [['JWT' => []]],
            ),
            paginationClientItemsPerPage: true,
            output: UserResource::class,
            name: self::PREFIX_NAME . '-col',
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
            input: UserPostInput::class,
            output: UserResource::class,
            name: self::PREFIX_NAME . '-create',
            processor: UserPostProcessor::class,
        ),
    ],
    order: ['createdAt' => 'DESC'],
    security: "is_granted('" . RoleSet::ROLE_ADMIN . "')",
    stateOptions: new Options(entityClass: User::class),
)]
final class UserManagementResource
{
    private const string PREFIX_NAME = 'users-admin';

    public string $id;
}
