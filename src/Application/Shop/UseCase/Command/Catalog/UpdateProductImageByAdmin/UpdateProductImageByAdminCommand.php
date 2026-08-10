<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Catalog\UpdateProductImageByAdmin;

use App\Application\Shared\CQRS\Command\CommandInterface;
use App\Application\Shared\Port\FileInterface;

final readonly class UpdateProductImageByAdminCommand implements CommandInterface
{
    public function __construct(
        public string $productId,
        public FileInterface $imageFile,
    ) {
    }
}
