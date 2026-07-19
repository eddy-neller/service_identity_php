<?php

declare(strict_types=1);

namespace App\Infrastructure\Tests\Unit\Service\Token;

use App\Domain\User\ValueObject\EmailAddress;
use App\Infrastructure\Service\Token\TokenProvider;
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

        $this->assertSame('dGVzdEBleGFtcGxlLmNvbSZ0ZXN0LXRva2VuLTEyMw==', $encodedToken);
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
        $result = $this->tokenProvider->split('dGVzdEBleGFtcGxlLmNvbSZ0ZXN0LXRva2VuLTEyMw==');

        $this->assertSame(
            [
                'email' => 'test@example.com',
                'token' => 'test-token-123',
            ],
            $result,
        );
    }
}
