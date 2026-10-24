<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Integration\Messaging\Sns;

use AsyncAws\Sns\SnsClient;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Le `SnsClient` du **conteneur** publie avec des requêtes bornées.
 *
 * Les tests unitaires de `BoundedSnsClientFactory` l'instancient à la main : aucun ne verrait la
 * fabrique retirée de `services.yaml`. Le client retrouverait alors les 300 s de
 * `default_socket_timeout` par tentative, et l'autowiring lui ôterait de nouveau ses nouvelles
 * tentatives — deux régressions sans aucune erreur.
 *
 * Seul `http_client` est remplacé : le client est celui de `services.yaml`, joint par l'alias
 * `test.sns_client` — sans autre usage en test, le conteneur l'aurait retiré.
 */
final class SnsClientWiringTest extends KernelTestCase
{
    public function testTheContainerPublishesWithBoundedRequests(): void
    {
        $maxDurations = [];
        self::getContainer()->set('http_client', new MockHttpClient(static function (string $method, string $url, array $options) use (&$maxDurations): MockResponse {
            $maxDurations[] = $options['max_duration'];

            return new MockResponse('<PublishResponse><PublishResult><MessageId>m-1</MessageId></PublishResult></PublishResponse>');
        }));

        $sns = self::getContainer()->get('test.sns_client');
        self::assertInstanceOf(SnsClient::class, $sns);
        $sns->publish(['TopicArn' => 'arn:aws:sns:eu-west-3:000000000000:identity-user-events', 'Message' => '{}'])->resolve();

        self::assertSame([10.0], $maxDurations);
    }
}
