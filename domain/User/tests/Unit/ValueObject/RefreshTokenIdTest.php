<?php

declare(strict_types=1);

namespace App\Domain\User\Tests\Unit\ValueObject;

use App\Domain\User\ValueObject\RefreshTokenId;
use PHPUnit\Framework\TestCase;

final class RefreshTokenIdTest extends TestCase
{
    public function testFromStringCreatesARefreshTokenId(): void
    {
        $id = RefreshTokenId::fromString('550e8400-e29b-41d4-a716-446655440001');

        $this->assertSame('550e8400-e29b-41d4-a716-446655440001', $id->toString());
    }
}
