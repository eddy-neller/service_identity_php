<?php

declare(strict_types=1);

namespace App\Infrastructure\Serializer\ContextBuilder;

use ApiPlatform\State\SerializerContextBuilderInterface;
use App\Domain\User\ValueObject\Security\RoleSet;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * ContextBuilder qui ajoute dynamiquement le groupe ':admin' aux groupes de sérialisation
 * si l'utilisateur authentifié est administrateur.
 *
 * Expose tous les champs réservés aux admins : published, statistiques, métadonnées, etc.
 */
#[AsDecorator(decorates: 'api_platform.serializer.context_builder')]
final readonly class AdminGroup implements SerializerContextBuilderInterface
{
    public function __construct(
        #[AutowireDecorated]
        private SerializerContextBuilderInterface $decorated,
        private Security $security,
    ) {
    }

    public function createFromRequest(Request $request, bool $normalization, ?array $extractedAttributes = null): array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);

        if ($this->security->isGranted(RoleSet::ROLE_ADMIN)) {
            $operation = $context['operation'] ?? null;

            if ($operation) {
                $shortName = new CamelCaseToSnakeCaseNameConverter()->normalize($operation->getShortName());

                // Ajouter le groupe {shortName}:admin (ex: partner:admin, site:admin)
                $adminGroup = [sprintf('%s:admin', $shortName)];

                $context['groups'] = $context['groups'] ?? [];
                $context['groups'] = array_unique(array_merge($context['groups'], $adminGroup));
            }
        }

        return $context;
    }
}
