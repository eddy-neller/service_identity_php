<?php

declare(strict_types=1);

namespace App\Domain\User\Tests\Unit\ValueObject;

use App\Domain\User\ValueObject\Avatar;
use PHPUnit\Framework\TestCase;

final class AvatarTest extends TestCase
{
    public function testConstructWithDefaultValues(): void
    {
        $avatar = new Avatar();

        $this->assertNull($avatar->fileName());
    }

    public function testConstructWithSpecificValues(): void
    {
        $avatar = new Avatar(
            fileName: 'avatar.jpg',
        );

        $this->assertSame('avatar.jpg', $avatar->fileName());
    }

    public function testWithFileCreatesNewInstance(): void
    {
        $avatar = new Avatar(fileName: 'old.jpg');
        $newAvatar = $avatar->withFile('new.jpg');

        $this->assertSame('old.jpg', $avatar->fileName());
        $this->assertSame('new.jpg', $newAvatar->fileName());
    }

    public function testWithFileIsImmutable(): void
    {
        $avatar = new Avatar(fileName: 'old.jpg');
        $newAvatar = $avatar->withFile('new.jpg');

        $this->assertNotSame($avatar, $newAvatar);
    }

    public function testWithFileCanSetNull(): void
    {
        $avatar = new Avatar(fileName: 'avatar.jpg');
        $newAvatar = $avatar->withFile(null);

        $this->assertNull($newAvatar->fileName());
    }
}
