<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\Sns;

use App\Infrastructure\Messaging\IntegrationBusUnavailableException;
use App\Infrastructure\Messaging\UserIntegrationEvent;
use App\Infrastructure\Messaging\UserIntegrationEventPublisherInterface;
use AsyncAws\Core\Exception\Http\ClientException;
use AsyncAws\Core\Exception\Http\NetworkException;
use AsyncAws\Core\Exception\Http\ServerException;
use AsyncAws\Sns\SnsClient;
use AsyncAws\Sns\ValueObject\MessageAttributeValue;

/**
 * Publie le contrat sur le topic SNS `identity-user-events`.
 *
 * Un topic, pas une file : ce service ne sait pas qui l'écoute, et n'a pas à le savoir. Chaque
 * consommateur possède sa propre file abonnée — `shop-user-events` est la première.
 *
 * L'attribut de message `type` double le champ `type` du corps. Il n'est pas redondant : seuls les
 * attributs sont lisibles par une **policy de filtrage d'abonnement**, ce qui permettra à un futur
 * abonné de ne recevoir que les types qui l'intéressent, sans que ce service change.
 *
 * > **Ne pas renommer cet attribut sans relire le consommateur.** Côté `service_shop`, les attributs
 * > SQS arrivent dans les mêmes en-têtes que le `type` du sérialiseur standard de Messenger, qui
 * > porte un nom de classe. Son `UserIntegrationEventSerializer` discrimine les deux par
 * > `class_exists()` — un attribut nommé autrement, ou portant un nom de classe, casserait ce
 * > départage.
 *
 * ## Le piège qui coûterait un événement perdu
 *
 * **`publish()` d'AsyncAws est asynchrone : il n'envoie rien tant que personne ne résout le
 * résultat.** Sans le `resolve()` ci-dessous, la requête partirait au mieux à la destruction de
 * l'objet, et une erreur HTTP ou réseau ne remonterait jamais. Le handler marquerait alors
 * l'événement comme traité, et le fait serait perdu **sans la moindre trace**. `resolve()` bloque
 * jusqu'à la réponse et lève sur erreur — c'est ce qui rend le retour de cette méthode digne de
 * confiance.
 *
 * ## Dire si attendre a un sens
 *
 * Trois échecs signifient « le bus est injoignable », et deviennent
 * {@see IntegrationBusUnavailableException} : le réseau (`NetworkException`, hôte introuvable ou
 * connexion refusée), une erreur 5xx de SNS (`ServerException`), et le throttling — HTTP 429, ou
 * HTTP 400 portant un code d'étranglement, la forme que prend SNS. Le handler les rejoue alors
 * sans limite, jusqu'au retour du bus.
 *
 * Quand l'une d'elles arrive ici, AsyncAws l'a **déjà** réessayée quelques fois en interne
 * (`AwsRetryStrategy`) : ce n'est pas un hoquet, c'est une indisponibilité. Ces tentatives, et la
 * borne de 10 s qui fait d'une connexion morte une `NetworkException`, viennent du client que
 * construit `BoundedSnsClientFactory` — l'autowiring les lui retirait.
 *
 * Toute autre erreur — `InvalidParameter`, droits refusés, topic inexistant — remonte **telle
 * quelle**. Rejouer une requête que SNS refuse ne la fera jamais passer : elle doit finir dans la file
 * d'échec, où un humain la verra.
 *
 * Traduire n'est pas attraper : l'exception remonte dans tous les cas, et le handler ne marque
 * jamais un événement dont la publication a échoué.
 *
 * Identifiants : aucun n'est passé ici. La chaîne par défaut d'AsyncAws s'en charge — variables
 * d'environnement en développement avec LocalStack, **EKS Pod Identity** en production, où le seul
 * droit nécessaire est `sns:Publish` sur ce topic.
 */
final readonly class SnsUserIntegrationEventPublisher implements UserIntegrationEventPublisherInterface
{
    /** Codes AWS d'étranglement : SNS les renvoie en HTTP 400, pas en 429. */
    private const array THROTTLING_CODES = ['Throttling', 'ThrottlingException', 'ThrottledException', 'RequestLimitExceeded'];

    public function __construct(
        private SnsClient $sns,
        private string $topicArn,
    ) {
    }

    public function publish(UserIntegrationEvent $event): void
    {
        $result = $this->sns->publish([
            'TopicArn' => $this->topicArn,
            'Message' => json_encode($event->toContract(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'MessageAttributes' => [
                'type' => new MessageAttributeValue([
                    'DataType' => 'String',
                    'StringValue' => $event->type,
                ]),
            ],
        ]);

        try {
            // Force l'envoi et fait remonter l'echec : voir le docblock de cette classe.
            $result->resolve();
        } catch (NetworkException|ServerException $e) {
            throw IntegrationBusUnavailableException::because($e);
        } catch (ClientException $e) {
            if ($this->isThrottling($e)) {
                throw IntegrationBusUnavailableException::because($e);
            }

            throw $e;
        }
    }

    private function isThrottling(ClientException $exception): bool
    {
        return 429 === $exception->getResponse()->getStatusCode()
            || in_array($exception->getAwsCode(), self::THROTTLING_CODES, true);
    }
}
