<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Unit\Messaging\Sns;

use App\Infrastructure\Messaging\Sns\BoundedSnsClientFactory;
use AsyncAws\Sns\SnsClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Éprouvé sur le **vrai** `SnsClient` : seul le transport HTTP est simulé.
 */
final class BoundedSnsClientFactoryTest extends TestCase
{
    private const array CONFIGURATION = [
        'region' => 'eu-west-3',
        'endpoint' => 'http://aws-local:4566',
        'accessKeyId' => 'k',
        'accessKeySecret' => 's',
    ];

    /**
     * Sans borne, une connexion coupée sans réponse bloquait le worker `domain_events` jusqu'aux
     * 300 s de `default_socket_timeout`, à chaque tentative.
     */
    public function testEveryPublicationIsBoundedInTime(): void
    {
        $maxDurations = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$maxDurations): MockResponse {
            $maxDurations[] = $options['max_duration'];

            return self::published();
        });

        self::assertTrue($this->publishThrough($client));
        self::assertSame([10.0], $maxDurations);
    }

    /**
     * Les nouvelles tentatives d'AsyncAws, que l'autowiring de son client HTTP lui retirait : une
     * erreur réseau passagère ne remonte plus au premier essai.
     */
    public function testATransientNetworkErrorIsRetried(): void
    {
        $requests = 0;
        $client = new MockHttpClient(static function () use (&$requests): MockResponse {
            return 1 === ++$requests
                ? new MockResponse('', ['error' => 'Could not resolve host: aws-local'])
                : self::published();
        });

        self::assertTrue($this->publishThrough($client));
        self::assertSame(2, $requests);
    }

    private function publishThrough(MockHttpClient $client): bool
    {
        $sns = (new BoundedSnsClientFactory($client, new NullLogger()))->create(self::CONFIGURATION);
        self::assertInstanceOf(SnsClient::class, $sns);

        return $sns->publish([
            'TopicArn' => 'arn:aws:sns:eu-west-3:000000000000:identity-user-events',
            'Message' => '{}',
        ])->resolve();
    }

    private static function published(): MockResponse
    {
        return new MockResponse(
            '<PublishResponse><PublishResult><MessageId>m-1</MessageId></PublishResult></PublishResponse>',
            ['response_headers' => ['content-type: text/xml']],
        );
    }
}
