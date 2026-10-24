<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\Sns;

use AsyncAws\Core\HttpClient\AwsRetryStrategy;
use AsyncAws\Sns\SnsClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Construit le client SNS avec des requêtes **bornées dans le temps** et les nouvelles tentatives
 * d'AsyncAws.
 *
 * ## La borne
 *
 * `publish()` attend sa réponse par un `resolve()` sans délai : seule l'inactivité de
 * `default_socket_timeout` l'arrête, soit 300 s ici. Une connexion coupée sans réponse — réseau
 * partitionné, interface retirée — bloquait donc le worker `domain_events` jusqu'à cinq minutes par
 * tentative, et avec lui tous les messages de la file, e-mails compris. Avec `max_duration`, curl
 * abandonne la requête : elle devient une `NetworkException`, que le publisher traduit en
 * indisponibilité, rejouée par Messenger jusqu'au retour du bus.
 *
 * Une publication abandonnée a pu atteindre SNS : la rejouer peut produire un doublon. C'est sans
 * conséquence, l'`eventId` étant transmis tel quel et dédupliqué par l'inbox de chaque consommateur.
 *
 * ## Les nouvelles tentatives
 *
 * AsyncAws n'enveloppe son client dans un `RetryableHttpClient` que si on ne lui en fournit aucun.
 * L'autowiring lui passait le `http_client` de Symfony : les 3 nouvelles tentatives sur une erreur
 * réseau, une 5xx ou un throttling avaient disparu sans bruit. Elles sont reconstruites ici à
 * l'identique (`AwsRetryStrategy` par défaut).
 *
 * La borne vaut pour la publication **entière**, tentatives comprises : `RetryableHttpClient` la
 * décompte depuis la première requête. Mesuré le 2026-10-24, worker détaché du réseau du bus sur une
 * connexion keep-alive : une publication rendue à Messenger en 10 s, contre 300 s auparavant.
 */
final readonly class BoundedSnsClientFactory
{
    /** Une publication répond en quelques millisecondes : 10 s ne coupent qu'une connexion morte. */
    public const int MAX_REQUEST_SECONDS = 10;

    private const int MAX_RETRIES = 3;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, string> $configuration
     */
    public function create(array $configuration): SnsClient
    {
        return new SnsClient($configuration, null, new RetryableHttpClient(
            $this->httpClient->withOptions(['max_duration' => self::MAX_REQUEST_SECONDS]),
            new AwsRetryStrategy(),
            self::MAX_RETRIES,
            $this->logger,
        ), $this->logger);
    }
}
