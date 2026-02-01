<?php

declare(strict_types=1);

namespace App\Infrastructure\Tests\Unit\Service\User;

use App\Infrastructure\Entity\User\User;
use App\Infrastructure\Persistence\Doctrine\User\UserRepository;
use App\Infrastructure\Service\User\TokenManager;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TokenManagerTest extends KernelTestCase
{
    /** @var UserRepository&MockObject */
    private UserRepository $userRepository;

    private TokenManager $tokenManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userRepository = $this->createMock(UserRepository::class);

        $this->tokenManager = new TokenManager(
            $this->userRepository,
        );
    }

    public function testSplitTokenSuccess(): void
    {
        $this->userRepository->expects($this->never())
            ->method('findInJsonField');

        $token = 'sometoken';
        $email = 'test@example.com';

        $encoded = $this->tokenManager->generateEmailToken($token, $email);
        $result = $this->tokenManager->splitToken($encoded);

        $this->assertEquals($email, $result['email']);
        $this->assertEquals($token, $result['token']);
    }

    public function testGenerateEmailToken(): void
    {
        $this->userRepository->expects($this->never())
            ->method('findInJsonField');

        $token = 'tok';
        $email = 'a@b.com';

        $encoded = $this->tokenManager->generateEmailToken($token, $email);

        $decoded = base64_decode($encoded);
        $this->assertStringContainsString($email, $decoded);
        $this->assertStringContainsString($token, $decoded);
    }

    public function testRetrieveUserReturnsUser(): void
    {
        $user = $this->createStub(User::class);
        $this->userRepository->expects($this->once())
            ->method('findInJsonField')
            ->willReturn($user);

        $result = $this->tokenManager->retrieveUser('activeEmail', 'token', 'abc123');

        $this->assertSame($user, $result);
    }

    public function testRetrieveUserReturnsNull(): void
    {
        $this->userRepository->expects($this->once())
            ->method('findInJsonField')
            ->willReturn(null);

        $result = $this->tokenManager->retrieveUser('activeEmail', 'token', 'nonexistent');

        $this->assertNull($result);
    }

    public function testClearJsonField(): void
    {
        $this->userRepository->expects($this->never())
            ->method('findInJsonField');

        $array = ['foo' => 'bar', 'baz' => 123];

        $result = $this->tokenManager->clearJsonField($array);

        $this->assertNull($result['foo']);
        $this->assertNull($result['baz']);
        $this->assertEquals(0, $result['mailSent']);
    }

    public function testClearJsonFieldWithEmptyArray(): void
    {
        $this->userRepository->expects($this->never())
            ->method('findInJsonField');

        $array = [];

        $result = $this->tokenManager->clearJsonField($array);

        $this->assertEquals(0, $result['mailSent']);
        $this->assertCount(1, $result);
    }

    public function testSplitTokenWithSpecialCharacters(): void
    {
        $this->userRepository->expects($this->never())
            ->method('findInJsonField');

        $token = 'token-with-special-chars!@#$%';
        $email = 'user+tag@example.com';

        $encoded = $this->tokenManager->generateEmailToken($token, $email);
        $result = $this->tokenManager->splitToken($encoded);

        $this->assertEquals($email, $result['email']);
        $this->assertEquals($token, $result['token']);
    }

    public function testGenerateEmailTokenFormat(): void
    {
        $this->userRepository->expects($this->never())
            ->method('findInJsonField');

        $token = 'testtoken123';
        $email = 'test@example.com';

        $encoded = $this->tokenManager->generateEmailToken($token, $email);

        $this->assertNotEmpty($encoded);

        $decoded = base64_decode($encoded);
        $expectedFormat = $email . TokenManager::TOKEN_SEPARATOR . $token;
        $this->assertSame($expectedFormat, $decoded);
    }

    public function testSplitTokenWithTokenContainingSeparator(): void
    {
        $this->userRepository->expects($this->never())
            ->method('findInJsonField');

        $token = 'token&with&separators';
        $email = 'test@example.com';

        $encoded = $this->tokenManager->generateEmailToken($token, $email);
        $result = $this->tokenManager->splitToken($encoded);

        $this->assertEquals($email, $result['email']);
        $this->assertEquals($token, $result['token']);
    }
}
