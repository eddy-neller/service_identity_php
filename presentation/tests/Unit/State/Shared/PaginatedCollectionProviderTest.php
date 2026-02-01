<?php

declare(strict_types=1);

namespace App\Presentation\Tests\Unit\State\Shared;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Presentation\Shared\State\PaginatedCollectionProvider;
use ArrayIterator;
use PHPUnit\Framework\MockObject\MockObject;
use stdClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

final class PaginatedCollectionProviderTest extends KernelTestCase
{
    private ProviderInterface&MockObject $provider;

    private Operation&MockObject $operation;

    private PaginatedCollectionProvider $paginColProvider;

    protected function setUp(): void
    {
        $this->provider = $this->createMock(ProviderInterface::class);
        $this->operation = $this->createMock(Operation::class);
        $this->operation->expects($this->never())
            ->method('getName');

        $this->paginColProvider = new PaginatedCollectionProvider($this->provider);
    }

    public function testProvideWithPaginatorInterfaceSetsAttributesAndReturnsArray(): void
    {
        $expectedArray = ['item1', 'item2', 'item3'];
        $totalItems = 25.0;
        $totalPages = 5.0;
        $request = new Request();
        $context = ['request' => $request];
        $paginator = new MockPaginator($expectedArray, $totalItems, $totalPages);

        $this->provider->expects($this->once())
            ->method('provide')
            ->with($this->operation, [], $context)
            ->willReturn($paginator);

        $result = $this->paginColProvider->provide($this->operation, [], $context);

        $this->assertSame($expectedArray, $result);
        $this->assertSame($totalItems, $request->attributes->get('_total_items'));
        $this->assertSame($totalPages, $request->attributes->get('_total_pages'));
    }

    public function testProvideWithPaginatorInterfaceWithoutRequestReturnsArray(): void
    {
        $expectedArray = ['item1', 'item2'];
        $context = [];
        $paginator = new MockPaginator($expectedArray, 10.0, 2.0);

        $this->provider->expects($this->once())
            ->method('provide')
            ->with($this->operation, [], $context)
            ->willReturn($paginator);

        $result = $this->paginColProvider->provide($this->operation, [], $context);

        $this->assertSame($expectedArray, $result);
    }

    public function testProvideWithArrayReturnsArrayWithAttributes(): void
    {
        $expectedArray = ['item1', 'item2', 'item3', 'item4'];
        $request = new Request();
        $context = ['request' => $request];

        $this->provider->expects($this->once())
            ->method('provide')
            ->with($this->operation, [], $context)
            ->willReturn($expectedArray);

        $result = $this->paginColProvider->provide($this->operation, [], $context);

        $this->assertSame($expectedArray, $result);
        $this->assertSame(4, $request->attributes->get('_total_items'));
        $this->assertSame(1, $request->attributes->get('_total_pages'));
    }

    public function testProvideWithArrayWithoutRequestReturnsArray(): void
    {
        $expectedArray = ['item1', 'item2'];
        $context = [];

        $this->provider->expects($this->once())
            ->method('provide')
            ->with($this->operation, [], $context)
            ->willReturn($expectedArray);

        $result = $this->paginColProvider->provide($this->operation, [], $context);

        $this->assertSame($expectedArray, $result);
    }

    public function testProvideWithIterableReturnsIterableWithAttributes(): void
    {
        $iterable = new ArrayIterator(['item1', 'item2', 'item3']);
        $request = new Request();
        $context = ['request' => $request];

        $this->provider->expects($this->once())
            ->method('provide')
            ->with($this->operation, [], $context)
            ->willReturn($iterable);

        $result = $this->paginColProvider->provide($this->operation, [], $context);

        $this->assertSame($iterable, $result);
        $this->assertSame(3, $request->attributes->get('_total_items'));
        $this->assertSame(1, $request->attributes->get('_total_pages'));
    }

    public function testProvideWithIterableWithoutRequestReturnsIterable(): void
    {
        $iterable = new ArrayIterator(['item1', 'item2']);
        $context = [];

        $this->provider->expects($this->once())
            ->method('provide')
            ->with($this->operation, [], $context)
            ->willReturn($iterable);

        $result = $this->paginColProvider->provide($this->operation, [], $context);

        $this->assertSame($iterable, $result);
    }

    public function testProvideWithObjectReturnsObject(): void
    {
        $expectedObject = new stdClass();
        $request = new Request();
        $context = ['request' => $request];

        $this->provider->expects($this->once())
            ->method('provide')
            ->with($this->operation, [], $context)
            ->willReturn($expectedObject);

        $result = $this->paginColProvider->provide($this->operation, [], $context);

        $this->assertSame($expectedObject, $result);
    }

    public function testProvideWithNullReturnsNull(): void
    {
        $request = new Request();
        $context = ['request' => $request];

        $this->provider->expects($this->once())
            ->method('provide')
            ->with($this->operation, [], $context)
            ->willReturn(null);

        $result = $this->paginColProvider->provide($this->operation, [], $context);

        $this->assertNull($result);
    }

    public function testProvideWithEmptyArraySetsCorrectAttributes(): void
    {
        $expectedArray = [];
        $request = new Request();
        $context = ['request' => $request];

        $this->provider->expects($this->once())
            ->method('provide')
            ->with($this->operation, [], $context)
            ->willReturn($expectedArray);

        $result = $this->paginColProvider->provide($this->operation, [], $context);

        $this->assertSame($expectedArray, $result);
        $this->assertSame(0, $request->attributes->get('_total_items'));
        $this->assertSame(1, $request->attributes->get('_total_pages'));
    }

    public function testProvideWithLargePaginatorSetsCorrectAttributes(): void
    {
        $expectedArray = array_fill(0, 100, 'item');
        $totalItems = 1000.0;
        $totalPages = 10.0;
        $request = new Request();
        $context = ['request' => $request];
        $paginator = new MockPaginator($expectedArray, $totalItems, $totalPages);

        $this->provider->expects($this->once())
            ->method('provide')
            ->with($this->operation, [], $context)
            ->willReturn($paginator);

        $result = $this->paginColProvider->provide($this->operation, [], $context);

        $this->assertSame($expectedArray, $result);
        $this->assertSame($totalItems, $request->attributes->get('_total_items'));
        $this->assertSame($totalPages, $request->attributes->get('_total_pages'));
    }

    public function testProvideWithMultipleCallsCallsProviderEachTime(): void
    {
        $expectedArray1 = ['item1'];
        $expectedArray2 = ['item2', 'item3'];
        $context = ['request' => new Request()];

        $this->provider->expects($this->exactly(2))
            ->method('provide')
            ->with($this->operation, [], $context)
            ->willReturnOnConsecutiveCalls($expectedArray1, $expectedArray2);

        $result1 = $this->paginColProvider->provide($this->operation, [], $context);
        $result2 = $this->paginColProvider->provide($this->operation, [], $context);

        $this->assertSame($expectedArray1, $result1);
        $this->assertSame($expectedArray2, $result2);
    }
}
