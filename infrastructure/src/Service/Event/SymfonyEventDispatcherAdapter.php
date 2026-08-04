<?php

declare(strict_types=1);

namespace App\Infrastructure\Service\Event;

use App\Application\Shared\Port\EventDispatcherInterface;
use App\Domain\SharedKernel\Event\DomainEventInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as SymfonyEventDispatcher;

final readonly class SymfonyEventDispatcherAdapter implements EventDispatcherInterface
{
    public function __construct(
        private SymfonyEventDispatcher $eventDispatcher,
    ) {
    }

    public function dispatchAll(array $events): void
    {
        foreach ($events as $event) {
            $this->dispatch($event);
        }
    }

    private function dispatch(DomainEventInterface $event): void
    {
        $this->eventDispatcher->dispatch($event, $event->eventName());
    }
}
