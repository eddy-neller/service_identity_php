<?php

declare(strict_types=1);

namespace App\Presentation\User\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use App\Presentation\User\Dto\Auth\LoginInput;
use App\Presentation\User\Dto\Auth\LogoutInput;
use App\Presentation\User\Dto\Auth\RefreshTokenInput;
use App\Presentation\User\State\Auth\LoginProcessor;
use App\Presentation\User\State\Auth\LogoutProcessor;
use App\Presentation\User\State\Auth\RefreshTokenProcessor;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    shortName: 'Auth',
    operations: [
        new Post(
            uriTemplate: '/login',
            status: 200,
            openapi: new Model\Operation(summary: 'Authenticate and issue an access and refresh token.'),
            normalizationContext: ['groups' => ['auth:read']],
            security: "is_granted('PUBLIC_ACCESS')",
            input: LoginInput::class,
            output: self::class,
            name: 'auth-login',
            processor: LoginProcessor::class,
        ),
        new Post(
            uriTemplate: '/token/refresh',
            status: 200,
            openapi: new Model\Operation(summary: 'Rotate a refresh token and issue a new token pair.'),
            normalizationContext: ['groups' => ['auth:read']],
            security: "is_granted('PUBLIC_ACCESS')",
            input: RefreshTokenInput::class,
            output: self::class,
            name: 'auth-refresh',
            processor: RefreshTokenProcessor::class,
        ),
        new Post(
            uriTemplate: '/token/invalidate',
            status: 204,
            openapi: new Model\Operation(summary: 'Revoke the current user refresh token.', security: [['JWT' => []]]),
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            input: LogoutInput::class,
            output: false,
            name: 'auth-logout',
            processor: LogoutProcessor::class,
        ),
    ],
    routePrefix: '/auth',
)]
final class AuthResource
{
    #[Groups(['auth:read'])]
    public string $accessToken;

    #[Groups(['auth:read'])]
    public string $refreshToken;

    #[Groups(['auth:read'])]
    public string $tokenType;

    #[Groups(['auth:read'])]
    public int $expiresIn;
}
