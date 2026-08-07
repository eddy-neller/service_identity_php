<?php

declare(strict_types=1);

namespace App\Infrastructure\Tests\Integration\Messenger;

use App\Domain\SharedKernel\Event\DomainEventInterface;
use App\Domain\User\Event\Lifecycle\ActivationEmailRequestedEvent;
use App\Domain\User\Event\Lifecycle\UserRegisteredEvent;
use App\Domain\User\ValueObject\Identity\EmailAddress;
use App\Domain\User\ValueObject\Identity\UserId;
use App\Infrastructure\Messenger\Event\MessengerDomainEventBus;
use App\Infrastructure\Messenger\Event\PublishedDomainEventCollector;
use App\Infrastructure\Persistence\Doctrine\DoctrineTransactional;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransportFactory;
use Symfony\Component\Messenger\Exception\InvalidMessageSignatureException;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SigningSerializer;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Atomicité de l'outbox avec un Doctrine réel.
 *
 * L'environnement de test route `domain_events` vers `sync://` pour préserver le
 * comportement des tests API : le transport Doctrine est donc reconstruit ici afin
 * d'exercer le vrai chemin de production.
 */
final class DomainEventOutboxTest extends KernelTestCase
{
    private const string QUEUE = 'domain_events';

    private const string USER_ID = '550e8400-e29b-41d4-a716-446655440000';

    private EntityManagerInterface $em;

    private MessengerDomainEventBus $eventBus;

    private DoctrineTransactional $transactional;

    private string $signingKey;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $registry = $kernel->getContainer()->get('doctrine');

        $this->em = $registry->getManager();
        $this->signingKey = (string) $kernel->getContainer()->getParameter('kernel.secret');
        $this->eventBus = new MessengerDomainEventBus(
            $this->buildBus($this->doctrineTransport($registry)),
            new PublishedDomainEventCollector(),
        );
        $this->transactional = new DoctrineTransactional($this->em);
    }

    public function testPublishedEventsAreCommittedWithTheTransaction(): void
    {
        $before = $this->countQueuedEvents();

        $this->transactional->transactional(function () use ($before): void {
            $this->eventBus->publishAll([$this->event()]);

            // Même connexion, donc la ligne est déjà visible avant le commit.
            $this->assertSame($before + 1, $this->countQueuedEvents());
        });

        $this->assertSame($before + 1, $this->countQueuedEvents());
    }

    public function testRollbackDiscardsPublishedEvents(): void
    {
        $before = $this->countQueuedEvents();

        try {
            $this->transactional->transactional(function (): void {
                $this->eventBus->publishAll([$this->event()]);

                throw new RuntimeException('Écriture métier en échec.');
            });
        } catch (RuntimeException) {
            // L'échec est propagé par `transactional()` après rollback : c'est le scénario testé.
        }

        $this->assertSame($before, $this->countQueuedEvents(), 'Aucun événement ne doit survivre au rollback.');
    }

    /**
     * Le ledger s'appuie sur `eventId()` : il doit traverser la sérialisation du transport,
     * de même que les Value Objects portés par l'événement.
     */
    public function testTheStoredMessageKeepsIdentityAndValueObjects(): void
    {
        // Événement porteur d'une `EmailAddress` : c'est celui qui exerce réellement la
        // déshydratation d'un Value Object, `UserRegisteredEvent` ne transportant qu'un id.
        $event = new ActivationEmailRequestedEvent(
            UserId::fromString(self::USER_ID),
            EmailAddress::fromString('john@example.com'),
            new DateTimeImmutable('2026-08-06 12:00:00'),
        );

        $this->transactional->transactional(function () use ($event): void {
            $this->eventBus->publishAll([$event]);
        });

        $row = $this->em->getConnection()->fetchAssociative(
            'SELECT body, headers FROM messenger_messages WHERE queue_name = :queue ORDER BY id DESC LIMIT 1',
            ['queue' => self::QUEUE],
        );

        $this->assertIsArray($row);

        $headers = json_decode((string) $row['headers'], true);
        $this->assertIsArray($headers);
        $this->assertSame(hash_hmac('sha256', (string) $row['body'], $this->signingKey), $headers['Body-Sign']);
        $this->assertSame('sha256', $headers['Sign-Algo']);

        $decoded = $this->signingSerializer()->decode([
            'body' => $row['body'],
            'headers' => $headers,
        ])->getMessage();

        $this->assertInstanceOf(ActivationEmailRequestedEvent::class, $decoded);
        $this->assertSame($event->eventId(), $decoded->eventId());
        $this->assertSame($event->aggregateId(), $decoded->aggregateId());
        $this->assertSame($event->getEmail()->toString(), $decoded->getEmail()->toString());
    }

    public function testTheStoredMessageSignatureRejectsTampering(): void
    {
        $this->transactional->transactional(function (): void {
            $this->eventBus->publishAll([$this->event()]);
        });

        $row = $this->em->getConnection()->fetchAssociative(
            'SELECT body, headers FROM messenger_messages WHERE queue_name = :queue ORDER BY id DESC LIMIT 1',
            ['queue' => self::QUEUE],
        );

        $this->assertIsArray($row);
        $headers = json_decode((string) $row['headers'], true);
        $this->assertIsArray($headers);

        $this->expectException(InvalidMessageSignatureException::class);

        $this->signingSerializer()->decode([
            'body' => $row['body'] . 'tampered',
            'headers' => $headers,
        ]);
    }

    private function doctrineTransport(object $registry): TransportInterface
    {
        return (new DoctrineTransportFactory($registry))->createTransport(
            'doctrine://default?queue_name=' . self::QUEUE . '&auto_setup=false',
            ['use_notify' => false],
            $this->signingSerializer(),
        );
    }

    private function buildBus(TransportInterface $transport): MessageBus
    {
        return new MessageBus([
            new SendMessageMiddleware(new SendersLocator(
                [DomainEventInterface::class => [self::QUEUE]],
                new ServiceLocator([self::QUEUE => static fn (): TransportInterface => $transport]),
            )),
        ]);
    }

    private function countQueuedEvents(): int
    {
        return (int) $this->em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM messenger_messages WHERE queue_name = :queue',
            ['queue' => self::QUEUE],
        );
    }

    private function signingSerializer(): SigningSerializer
    {
        return new SigningSerializer(new PhpSerializer(), $this->signingKey, [DomainEventInterface::class]);
    }

    private function event(): UserRegisteredEvent
    {
        return new UserRegisteredEvent(
            UserId::fromString(self::USER_ID),
            new DateTimeImmutable('2026-08-06 12:00:00'),
        );
    }
}
