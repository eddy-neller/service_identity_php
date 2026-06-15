<?php

declare(strict_types=1);

namespace App\Domain\Shop\Tests\Unit\ValueObject\Ordering;

use App\Domain\Shop\Ordering\ValueObject\PaymentSessionId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PaymentSessionIdTest extends TestCase
{
    public function testFromStringTrimsWhitespace(): void
    {
        $id = PaymentSessionId::fromString('  sess_123  ');

        $this->assertSame('sess_123', $id->toString());
    }

    public function testFromStringThrowsWhenEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment session id cannot be empty.');

        PaymentSessionId::fromString('   ');
    }

    public function testEqualsReturnsTrueForSameValue(): void
    {
        $id1 = PaymentSessionId::fromString('sess_123');
        $id2 = PaymentSessionId::fromString('sess_123');

        $this->assertTrue($id1->equals($id2));
    }

    public function testEqualsReturnsFalseForDifferentValue(): void
    {
        $id1 = PaymentSessionId::fromString('sess_123');
        $id2 = PaymentSessionId::fromString('sess_456');

        $this->assertFalse($id1->equals($id2));
    }

    public function testToStringCastsToString(): void
    {
        $id = PaymentSessionId::fromString('sess_123');

        $this->assertSame('sess_123', (string) $id);
    }
}
