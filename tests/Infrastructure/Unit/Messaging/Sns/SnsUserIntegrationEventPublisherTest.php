<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Unit\Messaging\Sns;

use App\Infrastructure\Messaging\IntegrationBusUnavailableException;
use App\Infrastructure\Messaging\Sns\SnsUserIntegrationEventPublisher;
use App\Infrastructure\Messaging\UserIntegrationEvent;
use AsyncAws\Core\Exception\Http\ClientException;
use AsyncAws\Core\Exception\Http\NetworkException;
use AsyncAws\Core\Exception\Http\ServerException;
use AsyncAws\Core\Response;
use AsyncAws\Core\Test\ResultMockFactory;
use AsyncAws\Sns\Result\PublishResponse;
use AsyncAws\Sns\SnsClient;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Throwable;

final class SnsUserIntegrationEventPublisherTest extends TestCase
{
    private const string TOPIC_ARN = 'arn:aws:sns:eu-west-3:000000000000:identity-user-events';

    private const string EVENT_ID = '018f3a40-0000-7000-8000-000000000001';

    private const string ACCOUNT_ID = '550e8400-e29b-41d4-a716-446655440011';

    public function testItPublishesTheContractOnTheTopic(): void
    {
        $input = null;

        $sns = $this->createMock(SnsClient::class);
        $sns->expects($this->once())->method('publish')
            ->willReturnCallback(function (array $given) use (&$input): PublishResponse {
                $input = $given;

                return ResultMockFactory::create(PublishResponse::class);
            });

        (new SnsUserIntegrationEventPublisher($sns, self::TOPIC_ARN))->publish($this->anEvent());

        self::assertIsArray($input);
        self::assertSame(self::TOPIC_ARN, $input['TopicArn']);
        self::assertSame(
            '{"id":"' . self::EVENT_ID . '","type":"identity.user.registered","version":1,'
            . '"occurredAt":"2026-09-15T12:00:00+00:00",'
            . '"data":{"userAccountId":"' . self::ACCOUNT_ID . '"}}',
            $input['Message'],
        );
    }

    /**
     * L'attribut de message double le champ `type` du corps : seuls les attributs sont lisibles par
     * une policy de filtrage d'abonnement.
     */
    public function testItCarriesTheTypeAsAMessageAttribute(): void
    {
        $input = null;

        // Un stub et non un mock : c'est l'attribut transmis qu'on verifie, pas l'appel.
        $sns = $this->createStub(SnsClient::class);
        $sns->method('publish')->willReturnCallback(
            function (array $given) use (&$input): PublishResponse {
                $input = $given;

                return ResultMockFactory::create(PublishResponse::class);
            },
        );

        (new SnsUserIntegrationEventPublisher($sns, self::TOPIC_ARN))->publish($this->anEvent());

        self::assertIsArray($input);
        $attribute = $input['MessageAttributes']['type'];
        self::assertSame('String', $attribute->getDataType());
        self::assertSame('identity.user.registered', $attribute->getStringValue());
    }

    /**
     * **Le test qui justifie le `resolve()`.** AsyncAws est asynchrone : sans lui, la requete
     * partirait au mieux a la destruction de l'objet et l'echec ne remonterait jamais. Le handler
     * marquerait alors l'evenement comme traite, et le fait serait perdu sans trace.
     *
     * Retirer le `resolve()` de l'adaptateur fait passer ce test au rouge, et lui seul.
     */
    public function testItFailsWhenSnsRejectsThePublication(): void
    {
        $sns = $this->createStub(SnsClient::class);
        $sns->method('publish')->willReturn(
            ResultMockFactory::createFailing(PublishResponse::class, 500, 'InternalError'),
        );

        $this->expectException(Throwable::class);

        (new SnsUserIntegrationEventPublisher($sns, self::TOPIC_ARN))->publish($this->anEvent());
    }

    /**
     * Le bus injoignable, sous ses trois formes. C'est ce qui fera rejouer la publication sans
     * limite : un faux negatif ici renverrait le fait dans la file d'echec au bout de dix minutes.
     *
     * La cause d'origine est gardee en `previous` : c'est elle que les logs du worker doivent montrer.
     */
    #[DataProvider('unreachableBus')]
    public function testItReportsAnUnreachableBusAsUnavailable(PublishResponse $failing, string $cause): void
    {
        $sns = $this->createStub(SnsClient::class);
        $sns->method('publish')->willReturn($failing);

        try {
            (new SnsUserIntegrationEventPublisher($sns, self::TOPIC_ARN))->publish($this->anEvent());
            self::fail('Un bus injoignable doit faire echouer la publication.');
        } catch (IntegrationBusUnavailableException $e) {
            self::assertInstanceOf($cause, $e->getPrevious());
        }
    }

    /**
     * @return iterable<string, array{PublishResponse, class-string<Throwable>}>
     */
    public static function unreachableBus(): iterable
    {
        yield 'hote introuvable (brique aws_local eteinte)' => [self::networkFailure(), NetworkException::class];
        yield 'erreur 5xx de SNS' => [
            ResultMockFactory::createFailing(PublishResponse::class, 503, 'Service Unavailable'),
            ServerException::class,
        ];
        yield 'throttling en 429' => [
            ResultMockFactory::createFailing(PublishResponse::class, 429, 'Too Many Requests'),
            ClientException::class,
        ];
        yield 'throttling en 400, la forme de SNS' => [
            ResultMockFactory::createFailing(PublishResponse::class, 400, 'Rate exceeded', ['__type' => 'Throttling']),
            ClientException::class,
        ];
    }

    /**
     * Le temoin du precedent : une requete que SNS refuse n'est **pas** une indisponibilite.
     * Attendre ne la ferait jamais passer, elle doit suivre le chemin ordinaire jusqu'a la file
     * d'echec. Traduire toute erreur en indisponibilite la ferait rejouer sans fin, en silence.
     */
    public function testItLetsARejectedRequestThroughUnchanged(): void
    {
        $sns = $this->createStub(SnsClient::class);
        $sns->method('publish')->willReturn(
            ResultMockFactory::createFailing(PublishResponse::class, 400, 'Invalid parameter: TopicArn', ['__type' => 'InvalidParameter']),
        );

        $this->expectException(ClientException::class);

        (new SnsUserIntegrationEventPublisher($sns, self::TOPIC_ARN))->publish($this->anEvent());
    }

    /**
     * Un echec de transport, sans la couche de retry d'AsyncAws : c'est ce que `ResultMockFactory`
     * construit lui-meme, avec une reponse en erreur reseau au lieu d'un statut HTTP.
     */
    private static function networkFailure(): PublishResponse
    {
        $client = new MockHttpClient(new MockResponse('', ['error' => 'Could not resolve host: aws-local']));

        return new PublishResponse(new Response($client->request('POST', 'http://aws-local:4566'), $client, new NullLogger()));
    }

    private function anEvent(): UserIntegrationEvent
    {
        return new UserIntegrationEvent(
            self::EVENT_ID,
            UserIntegrationEvent::TYPE_REGISTERED,
            self::ACCOUNT_ID,
            new DateTimeImmutable('2026-09-15T12:00:00+00:00'),
        );
    }
}
