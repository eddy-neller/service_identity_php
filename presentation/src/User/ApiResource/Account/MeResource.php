<?php

declare(strict_types=1);

namespace App\Presentation\User\ApiResource\Account;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use ApiPlatform\OpenApi\Model\RequestBody;
use App\Presentation\User\ApiResource\UserResource;
use App\Presentation\User\Dto\Account\Me\UserMeAvatarInput;
use App\Presentation\User\Dto\Account\Me\UserMePasswordUpdateInput;
use App\Presentation\User\State\Account\Me\UserMeAvatarProcessor;
use App\Presentation\User\State\Account\Me\UserMePasswordUpdateProcessor;
use App\Presentation\User\State\Account\Me\UserMeProvider;
use ArrayObject;

#[ApiResource(
    shortName: 'Me',
    operations: [
        new Get(
            uriTemplate: '/me',
            openapi: new Model\Operation(
                security: [['JWT' => []]]
            ),
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            output: UserResource::class,
            name: self::PREFIX_NAME,
            provider: UserMeProvider::class,
        ),
        new Post(
            uriTemplate: '/me/avatar',
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
            output: UserResource::class,
            name: self::PREFIX_NAME . '-avatar',
            processor: UserMeAvatarProcessor::class,
        ),
        new Patch(
            uriTemplate: '/me/update-password',
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
            name: self::PREFIX_NAME . '-update-password',
            processor: UserMePasswordUpdateProcessor::class,
        ),
    ],
    routePrefix: '/users',
)]
final class MeResource
{
    private const string PREFIX_NAME = 'users-me';
}
