<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Api\Shared;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * N'hérite pas de `BaseTest` : la sonde ne doit dépendre ni de la base, ni des fixtures.
 */
final class HealthTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    public function testHealthIsPublicAndNeverCached(): void
    {
        // Ni `Authorization` ni `Accept-Language` : la requête d'une sonde ou d'un simple `curl`.
        $response = self::createClient()->request('GET', '/health');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame(['status' => 'ok', 'service' => 'service_identity'], $response->toArray());
        self::assertStringContainsString('no-store', $response->getHeaders()['cache-control'][0] ?? '');
    }

    public function testHealthOnlyAnswersGet(): void
    {
        self::createClient()->request('POST', '/health');

        self::assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
    }
}
