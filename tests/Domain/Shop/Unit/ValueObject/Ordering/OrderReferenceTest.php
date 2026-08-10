<?php

declare(strict_types=1);

namespace App\Tests\Domain\Shop\Unit\ValueObject\Ordering;

use App\Domain\Shop\Ordering\ValueObject\OrderReference;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OrderReferenceTest extends TestCase
{
    public function testFromStringTrimsWhitespace(): void
    {
        $reference = OrderReference::fromString('  REF-123  ');

        $this->assertSame('REF-123', $reference->toString());
    }

    public function testFromStringThrowsWhenEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Order reference cannot be empty.');

        OrderReference::fromString('   ');
    }

    public function testFromStringThrowsWhenTooLong(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Order reference is too long.');

        OrderReference::fromString(str_repeat('a', 256));
    }

    public function testEqualsReturnsTrueForSameValue(): void
    {
        $ref1 = OrderReference::fromString('REF-123');
        $ref2 = OrderReference::fromString('REF-123');

        $this->assertTrue($ref1->equals($ref2));
    }

    public function testEqualsReturnsFalseForDifferentValue(): void
    {
        $ref1 = OrderReference::fromString('REF-123');
        $ref2 = OrderReference::fromString('REF-456');

        $this->assertFalse($ref1->equals($ref2));
    }

    public function testToStringCastsToString(): void
    {
        $reference = OrderReference::fromString('REF-123');

        $this->assertSame('REF-123', (string) $reference);
    }
}
