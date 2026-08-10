<?php

declare(strict_types=1);

namespace App\Presentation\User\ApiResource\Onboarding;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use ApiPlatform\OpenApi\Model\RequestBody;
use App\Presentation\User\ApiResource\UserResource;
use App\Presentation\User\Dto\Onboarding\UserActivationRequestInput;
use App\Presentation\User\Dto\Onboarding\UserActivationValidationInput;
use App\Presentation\User\Dto\Onboarding\UserRegisterInput;
use App\Presentation\User\State\Onboarding\UserActivationRequestProcessor;
use App\Presentation\User\State\Onboarding\UserActivationValidationProcessor;
use App\Presentation\User\State\Onboarding\UserRegisterProcessor;

#[ApiResource(
    shortName: 'Onboarding',
    operations: [
        new Post(
            uriTemplate: '',
            openapi: new Model\Operation(
                summary: 'Register a new user.',
                description: 'Register a new user account.',
                requestBody: new RequestBody(
                    description: 'User registration request body',
                    required: true
                ),
            ),
            input: UserRegisterInput::class,
            output: UserResource::class,
            name: self::PREFIX_NAME,
            processor: UserRegisterProcessor::class,
        ),
        new Post(
            uriTemplate: '/email-activation-request',
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
            name: self::PREFIX_NAME . '-resend',
            processor: UserActivationRequestProcessor::class,
        ),
        new Post(
            uriTemplate: '/validation',
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
            name: self::PREFIX_NAME . '-validation',
            processor: UserActivationValidationProcessor::class,
        ),
    ],
    routePrefix: '/users/register',
)]
final class OnboardingResource
{
    private const string PREFIX_NAME = 'users-register';
}
