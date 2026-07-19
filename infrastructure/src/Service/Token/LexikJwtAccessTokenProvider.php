<?php

declare(strict_types=1);

namespace App\Infrastructure\Service\Token;

use App\Application\User\Port\AccessTokenProviderInterface;
use App\Application\User\ReadModel\IssuedAccessToken;
use App\Domain\User\Model\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final readonly class LexikJwtAccessTokenProvider implements AccessTokenProviderInterface
{
    public function __construct(
        private JWTTokenManagerInterface $jwtTokenManager,
        private ParameterBagInterface $parameterBag,
    ) {
    }

    public function issue(User $user): IssuedAccessToken
    {
        $tokenUser = new JwtAccessTokenUser(
            email: $user->getEmail()->toString(),
            roles: $user->getRoles()->all(),
        );

        return new IssuedAccessToken(
            token: $this->jwtTokenManager->createFromPayload($tokenUser, [
                'id' => $user->getId()->toString(),
                'username' => $user->getUsername()->toString(),
            ]),
            expiresIn: (int) $this->parameterBag->get('jwt_ttl'),
        );
    }
}
