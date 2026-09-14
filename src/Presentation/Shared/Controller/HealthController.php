<?php

declare(strict_types=1);

namespace App\Presentation\Shared\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « L'application démarre » : nginx, php-fpm, le noyau Symfony et sa configuration répondent.
 *
 * Contrat, identique dans `service_shop` :
 * - aucune dépendance interrogée (ni base, ni Redis, ni broker) : une panne d'infrastructure ne
 *   fait pas échouer cette route ;
 * - publique (`access_control`), jamais routée par la passerelle ;
 * - `Cache-Control: no-store` explicite : aucun cache HTTP ne doit servir un « ok » périmé.
 *
 * Ce n'est **pas** une sonde liveness : elle exécute le code applicatif, listeners compris, qu'un
 * bug suffit à faire répondre 500 — et une liveness qui échoue redémarre le conteneur en boucle.
 * La liveness de php-fpm reposera sur son ping natif (feuille de route, étape 2).
 */
#[AsController]
final readonly class HealthController
{
    #[Route('/health', name: 'health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(
            ['status' => 'ok', 'service' => 'service_identity'],
            JsonResponse::HTTP_OK,
            ['Cache-Control' => 'no-store'],
        );
    }
}
