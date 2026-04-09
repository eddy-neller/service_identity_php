<?php

declare(strict_types=1);

namespace App\Presentation\Shared\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\PaginatorInterface;
use ApiPlatform\State\ProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class PaginatedCollectionProvider implements ProviderInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        private ProviderInterface $provider,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $result = $this->provider->provide($operation, $uriVariables, $context);

        $request = $context['request'] ?? null;

        // Cas 1 : Pagination activée - Le résultat est un PaginatorInterface
        if ($result instanceof PaginatorInterface) {
            if ($request) {
                $request->attributes->set('_total_items', $result->getTotalItems());
                $request->attributes->set('_total_pages', $result->getLastPage());
            }

            return iterator_to_array($result);
        }

        // Cas 2 : Pagination désactivée - Le résultat est iterable (array, Collection, etc.)
        // Dans ce cas, tous les éléments sont retournés, donc total_pages = 1
        if (is_iterable($result)) {
            $totalItems = is_array($result) ? count($result) : iterator_count($result);

            if ($request) {
                $request->attributes->set('_total_items', $totalItems);
                $request->attributes->set('_total_pages', 1);
            }

            return $result;
        }

        return $result;
    }
}
