<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\User\UseCase\Command;

use App\Application\User\UseCase\Command\Logout\LogoutCommand;
use PHPUnit\Framework\TestCase;

final class LogoutTest extends TestCase
{
    public function testCommandCarriesTheAuthenticatedUserAndRefreshToken(): void
    {
        $command = new LogoutCommand('550e8400-e29b-41d4-a716-446655440000', 'refresh-token');

        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $command->userId);
        $this->assertSame('refresh-token', $command->refreshToken);
    }
}
