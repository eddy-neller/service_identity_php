<?php

declare(strict_types=1);

namespace App\Infrastructure\Tests\Unit\Service\Token;

use App\Domain\User\ValueObject\EmailAddress;
use App\Infrastructure\Service\Token\TokenProvider;
use App\Infrastructure\Service\User\TokenManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class TokenProviderTest extends TestCase
{
    /** @var TokenManager&MockObject */
    private TokenManager $tokenManager;

    private TokenProvider $tokenProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tokenManager = $this->createMock(TokenManager::class);
        $this->tokenProvider = new TokenProvider($this->tokenManager);
    }

    public function testGenerateRandomToken(): void
    {
        $this->tokenManager->expects($this->never())
            ->method('generateEmailToken');
        $this->tokenManager->expects($this->never())
            ->method('splitToken');

        $token = $this->tokenProvider->generateRandomToken();

        $this->assertSame(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[0-9a-zA-Z]+$/', $token);
    }

    public function testGenerateRandomTokenReturnsDifferentValues(): void
    {
        $this->tokenManager->expects($this->never())
            ->method('generateEmailToken');
        $this->tokenManager->expects($this->never())
            ->method('splitToken');

        $token1 = $this->tokenProvider->generateRandomToken();
        $token2 = $this->tokenProvider->generateRandomToken();

        $this->assertNotSame($token1, $token2);
    }

    public function testEncode(): void
    {
        $token = 'test-token-123';
        $email = EmailAddress::fromString('test@example.com');
        $expectedEncoded = 'encoded-result';

        $this->tokenManager
            ->expects($this->once())
            ->method('generateEmailToken')
            ->with($token, 'test@example.com')
            ->willReturn($expectedEncoded);

        $result = $this->tokenProvider->encode($token, $email);

        $this->assertSame($expectedEncoded, $result);
    }

    public function testEncodeWithComplexEmail(): void
    {
        $token = 'complex-token!@#$%';
        $email = EmailAddress::fromString('user+tag@subdomain.example.com');
        $expectedEncoded = 'base64-encoded-string';

        $this->tokenManager
            ->expects($this->once())
            ->method('generateEmailToken')
            ->with($token, 'user+tag@subdomain.example.com')
            ->willReturn($expectedEncoded);

        $result = $this->tokenProvider->encode($token, $email);

        $this->assertSame($expectedEncoded, $result);
    }

    public function testSplit(): void
    {
        $encodedToken = 'some-encoded-token';
        $expectedResult = [
            'email' => 'test@example.com',
            'token' => 'decoded-token',
        ];

        $this->tokenManager
            ->expects($this->once())
            ->method('splitToken')
            ->with($encodedToken)
            ->willReturn($expectedResult);

        $result = $this->tokenProvider->split($encodedToken);

        $this->assertSame($expectedResult, $result);
    }

    public function testSplitReturnsCorrectStructure(): void
    {
        $encodedToken = 'encoded-string';
        $mockResult = [
            'email' => 'user@domain.com',
            'token' => 'token123',
        ];

        $this->tokenManager
            ->expects($this->once())
            ->method('splitToken')
            ->willReturn($mockResult);

        $result = $this->tokenProvider->split($encodedToken);

        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertEquals('user@domain.com', $result['email']);
        $this->assertEquals('token123', $result['token']);
    }
}
