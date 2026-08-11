<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Integration\Messenger;

use App\Application\Shared\Port\ClockInterface;
use App\Infrastructure\Persistence\Doctrine\DoctrineTransactional;
use App\Infrastructure\Symfony\Messenger\Event\DoctrineDomainEventLedger;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineDomainEventLedgerTest extends KernelTestCase
{
    private const string HANDLER = 'App\Tests\Infrastructure\Integration\Messenger\FakeHandler';

    private EntityManagerInterface $em;

    private DoctrineDomainEventLedger $ledger;

    private DoctrineTransactional $transactional;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->em = $kernel->getContainer()->get('doctrine')->getManager();

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-08-06 12:00:00'));

        $this->ledger = new DoctrineDomainEventLedger($this->em, $clock);
        $this->transactional = new DoctrineTransactional($this->em);
    }

    public function testAnUnknownEventIsNotConsideredProcessed(): void
    {
        $this->assertFalse($this->ledger->hasProcessed($this->eventId(), self::HANDLER));
    }

    public function testMarkingMakesTheEventProcessedForThatHandlerOnly(): void
    {
        $eventId = $this->eventId();

        $this->ledger->markProcessed($eventId, self::HANDLER);

        $this->assertTrue($this->ledger->hasProcessed($eventId, self::HANDLER));
        $this->assertFalse($this->ledger->hasProcessed($eventId, 'AnotherHandler'));
        $this->assertFalse($this->ledger->hasProcessed($this->eventId(), self::HANDLER));
    }

    /**
     * Deux workers peuvent marquer la même redélivrance : le second passage ne doit pas
     * remonter de violation de contrainte.
     */
    public function testMarkingTwiceIsHarmless(): void
    {
        $eventId = $this->eventId();

        $this->ledger->markProcessed($eventId, self::HANDLER);
        $this->ledger->markProcessed($eventId, self::HANDLER);

        $this->assertSame(1, $this->countEntries($eventId));
    }

    /**
     * C'est ce qui donne l'exactement-une-fois aux handlers à effet base : si l'effet est
     * annulé, la trace de traitement l'est aussi.
     */
    public function testMarkingIsRolledBackWithTheTransaction(): void
    {
        $eventId = $this->eventId();

        try {
            $this->transactional->transactional(function () use ($eventId): void {
                $this->ledger->markProcessed($eventId, self::HANDLER);

                throw new RuntimeException('Effet métier en échec.');
            });
        } catch (RuntimeException) {
            // L'échec est propagé par `transactional()` après rollback : c'est le scénario testé.
        }

        $this->assertFalse($this->ledger->hasProcessed($eventId, self::HANDLER));
    }

    private function countEntries(string $eventId): int
    {
        return (int) $this->em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM processed_domain_event WHERE event_id = :eventId',
            ['eventId' => $eventId],
        );
    }

    private function eventId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
