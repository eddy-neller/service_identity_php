<?php

declare(strict_types=1);

namespace App\Domain\Shop\Customer\Model;

use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Customer\ValueObject\CustomerStatus;
use App\Domain\Shop\Customer\ValueObject\UserAccountId;
use DateTimeImmutable;

final class Customer
{
    public const int MAX_ADDRESSES = 5;

    private function __construct(
        private CustomerId $id,
        private ?UserAccountId $userAccountId,
        private CustomerStatus $status,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {
    }

    public static function create(
        CustomerId $id,
        DateTimeImmutable $now,
        ?UserAccountId $userAccountId = null,
    ): self {
        return new self(
            id: $id,
            userAccountId: $userAccountId,
            status: CustomerStatus::active(),
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public static function reconstitute(
        CustomerId $id,
        CustomerStatus $status,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        ?UserAccountId $userAccountId = null,
    ): self {
        return new self(
            id: $id,
            userAccountId: $userAccountId,
            status: $status,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function activate(DateTimeImmutable $now): void
    {
        $this->status = CustomerStatus::active();
        $this->touch($now);
    }

    public function disable(DateTimeImmutable $now): void
    {
        $this->status = CustomerStatus::disabled();
        $this->touch($now);
    }

    public function getId(): CustomerId
    {
        return $this->id;
    }

    public function getUserAccountId(): ?UserAccountId
    {
        return $this->userAccountId;
    }

    public function getStatus(): CustomerStatus
    {
        return $this->status;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(DateTimeImmutable $now): void
    {
        $this->updatedAt = $now;
    }
}
