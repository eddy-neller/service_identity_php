<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Integration\Service\Hasher;

use App\Application\User\Port\PasswordHasherInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Garde-fou de configuration : `security.password_hashers` doit rester sur Argon2id.
 *
 * Repasser à `auto` retomberait silencieusement sur bcrypt cost 13 — NativePasswordHasher
 * initialise son algorithme à PASSWORD_BCRYPT et ne considère argon que si un algorithme
 * lui est explicitement passé. Une régression invisible autrement, qui triplerait le coût
 * du hash comme de la vérification au login.
 *
 * Le hasher exercé ici est celui réellement câblé dans le conteneur, pas un double.
 */
final class PasswordHasherAlgorithmTest extends KernelTestCase
{
    private const PLAIN_PASSWORD = 'Password123!';

    private PasswordHasherInterface $hasher;

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var PasswordHasherInterface $hasher */
        $hasher = self::getContainer()->get(PasswordHasherInterface::class);
        $this->hasher = $hasher;
    }

    public function testHashesUseArgon2id(): void
    {
        $this->assertStringStartsWith('$argon2id$', $this->hasher->hash(self::PLAIN_PASSWORD));
    }

    public function testHashVerifiesAgainstItsOwnPassword(): void
    {
        $this->assertTrue(
            $this->hasher->verify($this->hasher->hash(self::PLAIN_PASSWORD), self::PLAIN_PASSWORD),
        );
    }

    public function testHashRejectsAWrongPassword(): void
    {
        $this->assertFalse(
            $this->hasher->verify($this->hasher->hash(self::PLAIN_PASSWORD), 'not-the-password'),
        );
    }
}
