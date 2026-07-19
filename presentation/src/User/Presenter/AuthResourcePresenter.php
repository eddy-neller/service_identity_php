<?php

declare(strict_types=1);

namespace App\Presentation\User\Presenter;

use App\Application\User\ReadModel\AuthTokens;
use App\Presentation\User\ApiResource\AuthResource;

final class AuthResourcePresenter
{
    public function toResource(AuthTokens $tokens): AuthResource
    {
        $resource = new AuthResource();
        $resource->accessToken = $tokens->accessToken;
        $resource->refreshToken = $tokens->refreshToken;
        $resource->tokenType = $tokens->tokenType;
        $resource->expiresIn = $tokens->expiresIn;

        return $resource;
    }
}
