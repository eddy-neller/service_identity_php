<?php

declare(strict_types=1);

namespace App\Infrastructure\EventListener;

use DateTimeImmutable;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::RESPONSE)]
final readonly class LastModifiedListener
{
    public function __invoke(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        if (
            !$request->isMethodCacheable()
            || !$response->isSuccessful()
            || $response->headers->has('Last-Modified')
        ) {
            return;
        }

        $data = $request->attributes->get('data');
        if (null === $data) {
            return;
        }

        $lastModified = $this->resolveLastModified($data);
        if (null === $lastModified) {
            return;
        }

        $response->setLastModified($lastModified);
        $response->isNotModified($request);
    }

    private function resolveLastModified(mixed $data): ?DateTimeImmutable
    {
        if (is_iterable($data)) {
            $latest = null;

            foreach ($data as $item) {
                $itemDate = $this->extractDate($item);
                if (null === $itemDate) {
                    continue;
                }

                if (null === $latest || $itemDate->getTimestamp() > $latest->getTimestamp()) {
                    $latest = $itemDate;
                }
            }

            return $latest;
        }

        return $this->extractDate($data);
    }

    private function extractDate(mixed $item): ?DateTimeImmutable
    {
        if ($item instanceof DateTimeImmutable) {
            return $item;
        }

        if (is_array($item)) {
            if (isset($item['updatedAt']) && $item['updatedAt'] instanceof DateTimeImmutable) {
                return $item['updatedAt'];
            }

            if (isset($item['createdAt']) && $item['createdAt'] instanceof DateTimeImmutable) {
                return $item['createdAt'];
            }

            return null;
        }

        if (!is_object($item)) {
            return null;
        }

        if (method_exists($item, 'getUpdatedAt')) {
            $updatedAt = $item->getUpdatedAt();
            if ($updatedAt instanceof DateTimeImmutable) {
                return $updatedAt;
            }
        }

        if (method_exists($item, 'getCreatedAt')) {
            $createdAt = $item->getCreatedAt();
            if ($createdAt instanceof DateTimeImmutable) {
                return $createdAt;
            }
        }

        $publicVars = get_object_vars($item);
        if (isset($publicVars['updatedAt']) && $publicVars['updatedAt'] instanceof DateTimeImmutable) {
            return $publicVars['updatedAt'];
        }

        if (isset($publicVars['createdAt']) && $publicVars['createdAt'] instanceof DateTimeImmutable) {
            return $publicVars['createdAt'];
        }

        return null;
    }
}
