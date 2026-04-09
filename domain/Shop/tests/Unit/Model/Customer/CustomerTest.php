<?php

declare(strict_types=1);

namespace App\Domain\Shop\Tests\Unit\Model\Customer;

use App\Domain\Shop\Customer\Model\Customer;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Customer\ValueObject\CustomerStatus;
use App\Domain\Shop\Customer\ValueObject\UserAccountId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CustomerTest extends TestCase
{
    private const string CUSTOMER_ID = '550e8400-e29b-41d4-a716-446655440010';

    private const string ACCOUNT_ID = '550e8400-e29b-41d4-a716-446655440011';

    public function testRegisterCreatesActiveCustomer(): void
    {
        $now = new DateTimeImmutable('2025-01-01 10:00:00');
        $customer = Customer::create(
            id: CustomerId::fromString(self::CUSTOMER_ID),
            now: $now,
            userAccountId: UserAccountId::fromString(self::ACCOUNT_ID),
        );

        $this->assertTrue($customer->getId()->equals(CustomerId::fromString(self::CUSTOMER_ID)));
        $this->assertTrue($customer->getStatus()->isActive());
        $this->assertTrue($customer->getUserAccountId()?->equals(UserAccountId::fromString(self::ACCOUNT_ID)));
        $this->assertSame($now, $customer->getCreatedAt());
        $this->assertSame($now, $customer->getUpdatedAt());
    }

    public function testDisableSetsStatusAndTouchesUpdatedAt(): void
    {
        $createdAt = new DateTimeImmutable('2025-01-01 10:00:00');
        $customer = Customer::create(
            id: CustomerId::fromString(self::CUSTOMER_ID),
            now: $createdAt,
            userAccountId: UserAccountId::fromString(self::ACCOUNT_ID),
        );

        $disabledAt = new DateTimeImmutable('2025-01-02 10:00:00');
        $customer->disable($disabledAt);

        $this->assertTrue($customer->getStatus()->isDisabled());
        $this->assertSame($createdAt, $customer->getCreatedAt());
        $this->assertSame($disabledAt, $customer->getUpdatedAt());
    }

    public function testActivateSetsStatusAndTouchesUpdatedAt(): void
    {
        $createdAt = new DateTimeImmutable('2025-01-01 10:00:00');
        $customer = Customer::create(
            id: CustomerId::fromString(self::CUSTOMER_ID),
            now: $createdAt,
            userAccountId: UserAccountId::fromString(self::ACCOUNT_ID),
        );

        $disabledAt = new DateTimeImmutable('2025-01-02 10:00:00');
        $customer->disable($disabledAt);

        $activatedAt = new DateTimeImmutable('2025-01-03 10:00:00');
        $customer->activate($activatedAt);

        $this->assertTrue($customer->getStatus()->isActive());
        $this->assertSame($createdAt, $customer->getCreatedAt());
        $this->assertSame($activatedAt, $customer->getUpdatedAt());
    }

    public function testReconstituteRestoresStatus(): void
    {
        $createdAt = new DateTimeImmutable('2025-01-01 10:00:00');
        $updatedAt = new DateTimeImmutable('2025-01-03 10:00:00');

        $customer = Customer::reconstitute(
            id: CustomerId::fromString(self::CUSTOMER_ID),
            status: CustomerStatus::disabled(),
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            userAccountId: UserAccountId::fromString(self::ACCOUNT_ID),
        );

        $this->assertTrue($customer->getStatus()->isDisabled());
        $this->assertSame($createdAt, $customer->getCreatedAt());
        $this->assertSame($updatedAt, $customer->getUpdatedAt());
    }
}
