<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Api\Shared;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Infrastructure\Adapter\Token\AuthVersionStoreInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validation du JWT par le firewall, sur une vraie route protégée.
 *
 * `GET /api/users` exige `ROLE_ADMIN` au niveau de la ressource. L'expression ne lisant pas
 * l'objet, API Platform l'évalue **avant** le provider : aucun cas n'atteint Postgres. D'où
 * l'absence de `BaseTest` et de fixtures, comme pour `HealthTest`. Le claim `auth_version` est lu
 * dans `cache.jwt_auth`, un cache fichier en test : Redis n'est pas requis non plus.
 *
 * Le cas `ROLE_USER` -> 403 est le témoin : il prouve que les tokens forgés ici sont acceptés, donc
 * que chaque 401 tient au défaut injecté, et non à un token que le test aurait mal fabriqué.
 */
final class JwtAuthenticationTest extends ApiTestCase
{
    private const string PROTECTED_ROUTE = '/api/users';

    protected static ?bool $alwaysBootKernel = true;

    public function testMissingTokenIsUnauthorized(): void
    {
        $this->requestProtectedRoute(null);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testValidTokenIsAuthenticated(): void
    {
        $this->requestProtectedRoute(fn (): string => $this->forgeToken(['roles' => ['ROLE_USER']]));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testExpiredTokenIsRejected(): void
    {
        $this->requestProtectedRoute(
            fn (): string => $this->forgeToken(['roles' => ['ROLE_ADMIN'], 'exp' => time() - 60]),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Le cas qui compte : un porteur s'accorde `ROLE_ADMIN` en réécrivant le payload, sans pouvoir
     * re-signer. Retoucher le dernier caractère de la signature ne prouverait rien à coup sûr : ses
     * bits de poids faible sont du bourrage base64url, et le token pourrait rester valide.
     */
    public function testRewrittenPayloadIsRejected(): void
    {
        $this->requestProtectedRoute(function (): string {
            $parts = explode('.', $this->forgeToken(['roles' => ['ROLE_USER']]));

            $claims = json_decode($this->base64UrlDecode($parts[1]), true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($claims);
            $claims['roles'] = ['ROLE_ADMIN'];
            $parts[1] = $this->base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));

            return implode('.', $parts);
        });

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testUnsignedTokenIsRejected(): void
    {
        $this->requestProtectedRoute(function (): string {
            $userId = Uuid::uuid4()->toString();

            return $this->base64UrlEncode(json_encode(['alg' => 'none', 'typ' => 'JWT'], JSON_THROW_ON_ERROR))
                . '.' . $this->base64UrlEncode(json_encode([
                    'sub' => $userId,
                    'roles' => ['ROLE_ADMIN'],
                    'auth_version' => $this->authVersionStore()->getOrCreate($userId),
                    'exp' => time() + 900,
                ], JSON_THROW_ON_ERROR))
                . '.';
        });

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * La révocation de bout en bout : `RevokeSessionsHandler` fait tourner la version, et un token
     * émis avant la rotation cesse d'être accepté sans attendre son `exp`.
     */
    public function testTokenWithOutdatedAuthVersionIsRejected(): void
    {
        $this->requestProtectedRoute(function (): string {
            $userId = Uuid::uuid4()->toString();
            $token = $this->forgeToken(['sub' => $userId, 'roles' => ['ROLE_ADMIN']]);
            $this->authVersionStore()->rotate($userId);

            return $token;
        });

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testTokenWithoutAuthVersionIsRejected(): void
    {
        $this->requestProtectedRoute(
            fn (): string => $this->forgeToken(['roles' => ['ROLE_ADMIN']], withAuthVersion: false),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Intercepté par `JwtAuthVersionSubscriber`, qui valide le `sub` avant `JwtAuthenticatedUser`.
     */
    public function testNonUuidSubjectIsRejected(): void
    {
        $this->requestProtectedRoute(
            fn (): string => $this->forgeToken(['sub' => 'not-a-uuid', 'roles' => ['ROLE_ADMIN']]),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Le subscriber ne regarde pas `roles` : ce cas atteint `JwtAuthenticatedUser`, qui doit refuser
     * l'authentification plutôt que lever une erreur serveur.
     */
    public function testRolesThatAreNotAListAreRejected(): void
    {
        $this->requestProtectedRoute(fn (): string => $this->forgeToken(['roles' => 'ROLE_ADMIN']));

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Le token est fabriqué après `createClient()`, qui boote le noyau dont `forgeToken()` lit le
     * conteneur. `Accept: application/json` est explicite : le client de test d'API Platform envoie
     * `application/ld+json`, format que ce service n'expose pas — la route répondrait 406 dès que
     * l'erreur n'est plus rendue par lexik (absence de token, 403).
     *
     * @param (callable(): string)|null $token
     */
    private function requestProtectedRoute(?callable $token): void
    {
        $client = self::createClient();
        $options = ['headers' => ['Accept' => 'application/json']];

        if (null !== $token) {
            $options['auth_bearer'] = $token();
        }

        $client->request('GET', self::PROTECTED_ROUTE, $options);
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function forgeToken(array $claims, bool $withAuthVersion = true): string
    {
        $encoder = self::getContainer()->get(JWTEncoderInterface::class);

        if (!$encoder instanceof JWTEncoderInterface) {
            throw new RuntimeException('forgeToken: JWT encoder not found');
        }

        $subject = $claims['sub'] ?? Uuid::uuid4()->toString();
        self::assertIsString($subject);

        $claims += ['sub' => $subject, 'exp' => time() + 900];

        if ($withAuthVersion) {
            $claims += ['auth_version' => $this->authVersionStore()->getOrCreate($subject)];
        }

        return $encoder->encode($claims);
    }

    private function authVersionStore(): AuthVersionStoreInterface
    {
        $store = self::getContainer()->get(AuthVersionStoreInterface::class);

        if (!$store instanceof AuthVersionStoreInterface) {
            throw new RuntimeException('authVersionStore: auth-version store not found');
        }

        return $store;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/'), true);
    }
}
