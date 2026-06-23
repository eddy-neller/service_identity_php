<?php

declare(strict_types=1);

namespace App\Application\User\Port;

use App\Application\Shared\Port\FileInterface;
use App\Domain\User\ValueObject\Avatar;
use App\Domain\User\ValueObject\UserId;

interface AvatarUploaderInterface
{
    public function upload(UserId $id, FileInterface $file): Avatar;
}
