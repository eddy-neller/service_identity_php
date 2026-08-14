<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Unit\Http\ShopService;

use App\Infrastructure\Http\ShopService\LoggingShopCustomerClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class LoggingShopCustomerClientTest extends TestCase
{
    private const string USER_ID = '550e8400-e29b-41d4-a716-446655440000';

    private LoggerInterface&MockObject $logger;

    private LoggingShopCustomerClient $client;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->client = new LoggingShopCustomerClient($this->logger);
    }

    /**
     * Le contexte du log porte l'identifiant du compte : sans lui, la trace ne désignerait pas le
     * client à provisionner à la main, et le bouchon ne servirait à rien.
     */
    public function testItLogsTheProvisioningItDidNotPerform(): void
    {
        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->isString(), ['operation' => 'provision', 'user_account_id' => self::USER_ID]);

        $this->client->provisionCustomer(self::USER_ID);
    }

    public function testItLogsTheDeactivationItDidNotPerform(): void
    {
        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->isString(), ['operation' => 'disable', 'user_account_id' => self::USER_ID]);

        $this->client->disableCustomer(self::USER_ID);
    }

    /**
     * Ne jamais lever : le handler appelant marquerait l'événement comme non traité, Messenger
     * rejouerait six fois, et la file `failed` se remplirait de messages qu'aucun retry ne peut
     * satisfaire tant qu'aucun transport n'est branché.
     */
    public function testItNeverThrows(): void
    {
        $this->logger->expects($this->exactly(2))->method('warning');

        $this->client->provisionCustomer(self::USER_ID);
        $this->client->disableCustomer(self::USER_ID);

        $this->addToAssertionCount(1);
    }
}
