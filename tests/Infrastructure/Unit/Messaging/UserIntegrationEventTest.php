<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Unit\Messaging;

use App\Infrastructure\Messaging\UserIntegrationEvent;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Le contrat inter-services, vu du côté émetteur.
 *
 * Ce test et les fixtures JSON de `service_shop`
 * (`UserIntegrationEventSerializerTest`) décrivent la même chose depuis les deux rives. Si l'un
 * change sans l'autre, le bus se tait en production — ici, au moins, ça casse au rouge.
 */
final class UserIntegrationEventTest extends TestCase
{
    private const string EVENT_ID = '018f3a40-0000-7000-8000-000000000001';

    private const string ACCOUNT_ID = '550e8400-e29b-41d4-a716-446655440011';

    public function testItRendersTheContractOfVersionOne(): void
    {
        $event = new UserIntegrationEvent(
            self::EVENT_ID,
            UserIntegrationEvent::TYPE_REGISTERED,
            self::ACCOUNT_ID,
            new DateTimeImmutable('2026-09-15T12:00:00+00:00'),
        );

        self::assertSame([
            'id' => self::EVENT_ID,
            'type' => 'identity.user.registered',
            'version' => 1,
            'occurredAt' => '2026-09-15T12:00:00+00:00',
            'data' => ['userAccountId' => self::ACCOUNT_ID],
        ], $event->toContract());
    }

    /**
     * La date est publiée en ISO-8601 avec décalage explicite. Le consommateur la relit de façon
     * permissive, mais l'émetteur, lui, fait foi : pas de `Z`, pas de microsecondes, pas d'heure
     * locale implicite.
     */
    public function testItPublishesTheDateWithAnExplicitOffset(): void
    {
        $event = new UserIntegrationEvent(
            self::EVENT_ID,
            UserIntegrationEvent::TYPE_DELETED,
            self::ACCOUNT_ID,
            new DateTimeImmutable('2026-09-15 14:30:05', new \DateTimeZone('Europe/Paris')),
        );

        self::assertSame('2026-09-15T14:30:05+02:00', $event->toContract()['occurredAt']);
    }

    /**
     * Les trois types sont **le** contrat : les renommer casse les consommateurs sans qu'aucun
     * test de ce dépôt ne s'en aperçoive. D'où leur présence en dur ici.
     */
    public function testTheThreeContractTypesAreStable(): void
    {
        self::assertSame('identity.user.registered', UserIntegrationEvent::TYPE_REGISTERED);
        self::assertSame('identity.user.created_by_admin', UserIntegrationEvent::TYPE_CREATED_BY_ADMIN);
        self::assertSame('identity.user.deleted', UserIntegrationEvent::TYPE_DELETED);
        self::assertSame(1, UserIntegrationEvent::VERSION);
    }
}
