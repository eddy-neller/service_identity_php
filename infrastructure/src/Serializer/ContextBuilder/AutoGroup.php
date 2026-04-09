<?php

declare(strict_types=1);

namespace App\Infrastructure\Serializer\ContextBuilder;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\SerializerContextBuilderInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

#[AsDecorator(decorates: 'api_platform.serializer.context_builder')]
final readonly class AutoGroup implements SerializerContextBuilderInterface
{
    public function __construct(
        #[AutowireDecorated]
        private SerializerContextBuilderInterface $decorated,
    ) {
    }

    public function createFromRequest(Request $request, bool $normalization, ?array $extractedAttributes = null): array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);
        $operation = $context['operation'] ?? null;

        if ($operation) {
            if (!empty($context['groups'])) {
                $context['groups'] = array_unique(array_merge($context['groups'], $this->getDefaultGroups($operation, $normalization)));
            } else {
                $context['groups'] = $this->getDefaultGroups($operation, $normalization);
            }
        }

        return $context;
    }

    /**
     * Create custom groups for a given operation.
     */
    private function getDefaultGroups(object $operation, bool $normalization): array
    {
        /* The shortName is basically the entity name converted in camel case */
        $shortName = new CamelCaseToSnakeCaseNameConverter()->normalize($operation->getShortName());
        $operationName = $operation->getName();
        $readOrWrite = $normalization ? 'read' : 'write';
        $itemOrCol = $operation instanceof GetCollection ? 'col' : 'item';

        return [
            /*
             * {read/write}
             * e.g. read
             */
            $readOrWrite,
            /*
             * {shortName}
             * e.g. user
             */
            $shortName,
            /*
             * {shortName}:{read/write}
             * e.g. user:read
             */
            sprintf('%s:%s', $shortName, $readOrWrite),
            /*
             * {shortName}:{item/collection}:{read/write}
             * e.g. user:item:read
             */
            sprintf('%s:%s:%s', $shortName, $itemOrCol, $readOrWrite),
            /*
             * operationName is the name associated to the operation in ApiResource. For example : register or bulk_assignment.
             *
             * {shortName}:{item/collection}:{operationName}
             * e.g. user:item:put or user:item:avatar
             */
            sprintf('%s:%s:%s', $shortName, $itemOrCol, $operationName),
            /*
             * operationName is the name associated to the operation in ApiResource. For example : register or bulk_assignment.
             *
             * {shortName}:{item/collection}:{operationName}:{read/write}
             * e.g. user:item:avatar:read
             */
            sprintf('%s:%s:%s:%s', $shortName, $itemOrCol, $operationName, $readOrWrite),
        ];
    }
}
