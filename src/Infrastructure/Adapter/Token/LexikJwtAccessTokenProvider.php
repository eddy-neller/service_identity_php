<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter\Token;

use App\Application\User\Port\AccessTokenProviderInterface;
use App\Domain\User\Model\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final readonly class LexikJwtAccessTokenProvider implements AccessTokenProviderInterface
{
    public function __construct(
        private JWTTokenManagerInterface $jwtTokenManager,
        private ParameterBagInterface $parameterBag,
        private AuthVersionStoreInterface $authVersionStore,
    ) {
    }

    /**
     * @return array{token: string, expiresIn: int}
     */
    public function issue(User $user): array
    {
        $tokenUser = new JwtAccessTokenUser(
            userId: $user->getId()->toString(),
            roles: $user->getRoles()->all(),
        );

        return [
            'token' => $this->jwtTokenManager->createFromPayload($tokenUser, [
                'auth_version' => $this->authVersionStore->getOrCreate($user->getId()->toString()),
            ]),
            'expiresIn' => (int) $this->parameterBag->get('jwt_ttl'),
        ];
    }
}
