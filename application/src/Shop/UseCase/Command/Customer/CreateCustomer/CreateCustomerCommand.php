<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Customer\CreateCustomer;

use App\Application\Shared\CQRS\Command\CommandInterface;
use App\Domain\Shop\Customer\ValueObject\UserAccountId;

final readonly class CreateCustomerCommand implements CommandInterface
{
    public function __construct(
        public UserAccountId $userAccountId,
    ) {
    }
}
