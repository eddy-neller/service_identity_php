<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Unit\Adapter\Token;

use App\Domain\User\ValueObject\Identity\EmailAddress;
use App\Infrastructure\Adapter\Token\TokenProvider;
use PHPUnit\Framework\TestCase;

final class TokenProviderTest extends TestCase
{
    private TokenProvider $tokenProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tokenProvider = new TokenProvider();
    }

    public function testGenerateRandomToken(): void
    {
        $token = $this->tokenProvider->generateRandomToken();

        $this->assertSame(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[0-9a-zA-Z]+$/', $token);
    }

    public function testGenerateRandomTokenWithCustomLength(): void
    {
        $token = $this->tokenProvider->generateRandomToken(32);

        $this->assertSame(32, strlen($token));
        $this->assertMatchesRegularExpression('/^[0-9a-zA-Z]+$/', $token);
    }

    public function testGenerateRandomTokenReturnsDifferentValues(): void
    {
        $token1 = $this->tokenProvider->generateRandomToken();
        $token2 = $this->tokenProvider->generateRandomToken();

        $this->assertNotSame($token1, $token2);
    }

    public function testEncode(): void
    {
        $encodedToken = $this->tokenProvider->encode(
            'test-token-123',
            EmailAddress::fromString('test@example.com'),
        );

        $this->assertSame('dGVzdEBleGFtcGxlLmNvbSZ0ZXN0LXRva2VuLTEyMw', $encodedToken);
    }

    /**
     * Le jeton part dans l'URL d'un e-mail : aucun caractère ne doit y nécessiter de
     * percent-encoding, sans quoi un client qui oublie de décoder corrompt le jeton.
     */
    public function testEncodeProducesUrlSafeOutput(): void
    {
        // Charge utile choisie pour produire `+` et `/` en base64 standard.
        $encodedToken = $this->tokenProvider->encode(
            "\xFB\xEF\xBE-token",
            EmailAddress::fromString('test@example.com'),
        );

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $encodedToken);
        $this->assertSame($encodedToken, rawurlencode($encodedToken));
    }

    public function testSplitRejectsATokenLeftPercentEncoded(): void
    {
        $encodedToken = $this->tokenProvider->encode(
            'test-token-123',
            EmailAddress::fromString('test@example.com'),
        );

        // `%3D` : le padding `=` d'un ancien jeton base64 resté encodé. En mode permissif
        // le `%` était sauté mais les `3` et `D` absorbés comme données.
        $this->assertNull($this->tokenProvider->split($encodedToken . '%3D'));
    }

    public function testSplitRejectsGarbage(): void
    {
        $this->assertNull($this->tokenProvider->split('*** pas du base64 ***'));
    }

    public function testSplitRejectsAPayloadWithoutSeparator(): void
    {
        $this->assertNull($this->tokenProvider->split(rtrim(base64_encode('no-separator-here'), '=')));
    }

    public function testEncodeAndSplitWithComplexEmailAndToken(): void
    {
        $token = 'complex-token!@#$%';
        $email = EmailAddress::fromString('user+tag@subdomain.example.com');

        $encodedToken = $this->tokenProvider->encode($token, $email);

        $this->assertSame(
            [
                'email' => 'user+tag@subdomain.example.com',
                'token' => 'complex-token!@#$%',
            ],
            $this->tokenProvider->split($encodedToken),
        );
    }

    public function testSplit(): void
    {
        $result = $this->tokenProvider->split('dGVzdEBleGFtcGxlLmNvbSZ0ZXN0LXRva2VuLTEyMw');

        $this->assertSame(
            [
                'email' => 'test@example.com',
                'token' => 'test-token-123',
            ],
            $result,
        );
    }
}
