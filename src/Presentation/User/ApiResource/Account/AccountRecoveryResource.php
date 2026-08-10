<?php

declare(strict_types=1);

namespace App\Presentation\User\ApiResource\Account;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use ApiPlatform\OpenApi\Model\RequestBody;
use App\Presentation\User\Dto\Account\AccountRecovery\PasswordResetCheckInput;
use App\Presentation\User\Dto\Account\AccountRecovery\PasswordResetConfirmInput;
use App\Presentation\User\Dto\Account\AccountRecovery\PasswordResetRequestInput;
use App\Presentation\User\State\Account\AccountRecovery\PasswordResetCheckProcessor;
use App\Presentation\User\State\Account\AccountRecovery\PasswordResetConfirmProcessor;
use App\Presentation\User\State\Account\AccountRecovery\PasswordResetRequestProcessor;

#[ApiResource(
    shortName: 'AccountRecovery',
    operations: [
        new Post(
            uriTemplate: '/request',
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
            name: self::PREFIX_NAME . '-request',
            processor: PasswordResetRequestProcessor::class,
        ),
        new Post(
            uriTemplate: '/check',
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
            name: self::PREFIX_NAME . '-check',
            processor: PasswordResetCheckProcessor::class,
        ),
        new Post(
            uriTemplate: '/confirm',
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
            name: self::PREFIX_NAME . '-confirm',
            processor: PasswordResetConfirmProcessor::class,
        ),
    ],
    routePrefix: '/users/reset-password',
)]
final class AccountRecoveryResource
{
    private const string PREFIX_NAME = 'users-password-reset';
}
