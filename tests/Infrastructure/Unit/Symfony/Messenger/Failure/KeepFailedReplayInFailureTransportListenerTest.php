<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Unit\Symfony\Messenger\Failure;

use App\Infrastructure\Symfony\Messenger\Failure\KeepFailedReplayInFailureTransportListener;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use stdClass;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * L'écouteur est éprouvé avec le **vrai** `SendFailedMessageToFailureTransportListener` de Messenger,
 * aux mêmes priorités qu'en production : c'est leur interaction qui compte — le renvoi dans la bonne
 * file, et la neutralisation de l'envoi vers la file globale.
 *
 * Les transports reproduisent la configuration du service : `async` échoue vers `failed_async`, et
 * toute file d'échec, faute de réglage propre, vers la globale `failed_domain_events` — qui est donc
 * sa propre file d'échec.
 */
final class KeepFailedReplayInFailureTransportListenerTest extends TestCase
{
    private InMemoryTransport $failedAsync;

    private InMemoryTransport $failedDomainEvents;

    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->failedAsync = new InMemoryTransport();
        $this->failedDomainEvents = new InMemoryTransport();

        $transports = new ServiceLocator([
            'failed_async' => fn (): InMemoryTransport => $this->failedAsync,
            'failed_domain_events' => fn (): InMemoryTransport => $this->failedDomainEvents,
        ]);

        $this->dispatcher = new EventDispatcher();
        $this->dispatcher->addListener(
            WorkerMessageFailedEvent::class,
            new KeepFailedReplayInFailureTransportListener($transports, new NullLogger()),
            -50,
        );
        $this->dispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener(
            new ServiceLocator([
                'async' => fn (): InMemoryTransport => $this->failedAsync,
                'failed_async' => fn (): InMemoryTransport => $this->failedDomainEvents,
                'failed_domain_events' => fn (): InMemoryTransport => $this->failedDomainEvents,
            ]),
            null,
            [
                'async' => 'failed_async',
                'failed_async' => 'failed_domain_events',
                'failed_domain_events' => 'failed_domain_events',
            ],
        ));
    }

    /**
     * **La perte d'origine.** La file globale est sa propre file d'échec : Messenger renonce à l'y
     * renvoyer, et le worker supprime le message. Ici, il y retourne.
     */
    public function testAReplayFromTheGlobalFailureTransportIsNotDropped(): void
    {
        $this->failOn('failed_domain_events', $this->replayedFrom('domain_events'));

        self::assertCount(1, $this->failedDomainEvents->getSent());
        self::assertSame(
            'domain_events',
            $this->failedDomainEvents->getSent()[0]->last(SentToFailureTransportStamp::class)?->getOriginalReceiverName(),
            'Le transport d origine reste celui qu affiche messenger:failed:show.',
        );
    }

    /**
     * Rejoué depuis `failed_async` et toujours en échec : il y retourne, au lieu de finir dans la file
     * des Domain Events, où un second rejeu raté l'aurait effacé.
     */
    public function testAReplayThatFailsAgainGoesBackToItsFailureTransport(): void
    {
        $this->failOn('failed_async', $this->replayedFrom('async'));

        self::assertCount(1, $this->failedAsync->getSent());
        self::assertCount(0, $this->failedDomainEvents->getSent(), 'Aucun doublon dans la file globale.');
    }

    /**
     * Le témoin : un échec ordinaire, hors rejeu, garde le chemin de Messenger.
     */
    public function testAFirstFailureKeepsTheOrdinaryPath(): void
    {
        $this->failOn('async', new Envelope(new stdClass()));

        self::assertCount(1, $this->failedAsync->getSent());
        self::assertCount(0, $this->failedDomainEvents->getSent());
    }

    /**
     * Un rejeu que Messenger va réessayer est déjà renvoyé dans sa file par le retry : l'écouteur ne
     * fait rien, sans quoi il le doublerait.
     */
    public function testAReplayThatWillBeRetriedIsLeftToTheRetry(): void
    {
        $event = new WorkerMessageFailedEvent($this->replayedFrom('domain_events'), 'failed_domain_events', new RuntimeException('again'));
        $event->setForRetry();

        $this->dispatcher->dispatch($event);

        self::assertCount(0, $this->failedAsync->getSent());
        self::assertCount(0, $this->failedDomainEvents->getSent());
    }

    private function replayedFrom(string $originalTransport): Envelope
    {
        return new Envelope(new stdClass(), [new SentToFailureTransportStamp($originalTransport)]);
    }

    private function failOn(string $receiverName, Envelope $envelope): void
    {
        $this->dispatcher->dispatch(new WorkerMessageFailedEvent($envelope, $receiverName, new RuntimeException('again')));
    }
}
