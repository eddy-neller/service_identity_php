<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Unit\Messaging\Sns;

use App\Infrastructure\Messaging\Sns\SnsUserIntegrationEventPublisher;
use App\Infrastructure\Messaging\UserIntegrationEvent;
use AsyncAws\Core\Test\ResultMockFactory;
use AsyncAws\Sns\Result\PublishResponse;
use AsyncAws\Sns\SnsClient;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
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
