<?php

declare(strict_types=1);

namespace App\Infrastructure\Tests\Unit\Persistence;

use App\Infrastructure\Entity\RefreshToken;
use App\Infrastructure\Persistence\Doctrine\RefreshTokenRepository;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RefreshTokenRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    private RefreshTokenRepository $repo;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->em = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();

        /** @var RefreshTokenRepository $repo */
        $repo = $this->em->getRepository(RefreshToken::class);
        $this->repo = $repo;
    }

    public function testFindInvalidReturnsExpiredTokens(): void
    {
        // Créer un token expiré (date dans le passé)
        $expiredToken = $this->createRefreshToken([
            'refreshToken' => 'expired-token-123',
            'username' => 'user@example.com',
            'valid' => new DateTimeImmutable('-1 day')->format('Y-m-d H:i:s'),
        ]);
        $this->em->persist($expiredToken);

        // Créer un token valide (date dans le futur)
        $validToken = $this->createRefreshToken([
            'refreshToken' => 'valid-token-456',
            'username' => 'user2@example.com',
            'valid' => new DateTimeImmutable('+1 day')->format('Y-m-d H:i:s'),
        ]);
        $this->em->persist($validToken);

        $this->em->flush();

        // Rechercher les tokens expirés
        $invalidTokens = $this->repo->findInvalid();

        $this->assertCount(1, $invalidTokens);
        $this->assertSame('expired-token-123', $invalidTokens[0]->getRefreshToken());

        // Cleanup
        $this->em->remove($expiredToken);
        $this->em->remove($validToken);
        $this->em->flush();
    }

    public function testFindInvalidWithCustomDate(): void
    {
        // Token qui expire dans 2 heures
        $tokenExpiring2Hours = $this->createRefreshToken([
            'refreshToken' => 'token-2h-789',
            'username' => 'user3@example.com',
            'valid' => new DateTimeImmutable('+2 hours')->format('Y-m-d H:i:s'),
        ]);
        $this->em->persist($tokenExpiring2Hours);

        // Token qui expire dans 5 heures
        $tokenExpiring5Hours = $this->createRefreshToken([
            'refreshToken' => 'token-5h-101',
            'username' => 'user4@example.com',
            'valid' => new DateTimeImmutable('+5 hours')->format('Y-m-d H:i:s'),
        ]);
        $this->em->persist($tokenExpiring5Hours);

        $this->em->flush();

        // Rechercher les tokens expirés avant +3 heures
        $customDate = new DateTime('+3 hours');
        $invalidTokens = $this->repo->findInvalid($customDate);

        $this->assertCount(1, $invalidTokens);
        $this->assertSame('token-2h-789', $invalidTokens[0]->getRefreshToken());

        // Cleanup
        $this->em->remove($tokenExpiring2Hours);
        $this->em->remove($tokenExpiring5Hours);
        $this->em->flush();
    }

    public function testFindInvalidReturnsEmptyArrayWhenNoExpiredTokens(): void
    {
        // Créer uniquement des tokens valides
        $validToken1 = $this->createRefreshToken([
            'refreshToken' => 'valid-token-111',
            'username' => 'user5@example.com',
            'valid' => new DateTimeImmutable('+1 day')->format('Y-m-d H:i:s'),
        ]);
        $this->em->persist($validToken1);

        $validToken2 = $this->createRefreshToken([
            'refreshToken' => 'valid-token-222',
            'username' => 'user6@example.com',
            'valid' => new DateTimeImmutable('+2 days')->format('Y-m-d H:i:s'),
        ]);
        $this->em->persist($validToken2);

        $this->em->flush();

        $invalidTokens = $this->repo->findInvalid();

        $this->assertCount(0, $invalidTokens);

        // Cleanup
        $this->em->remove($validToken1);
        $this->em->remove($validToken2);
        $this->em->flush();
    }

    public function testFindInvalidReturnsMultipleExpiredTokens(): void
    {
        // Créer plusieurs tokens expirés
        $expiredToken1 = $this->createRefreshToken([
            'refreshToken' => 'expired-token-333',
            'username' => 'user7@example.com',
            'valid' => new DateTimeImmutable('-2 days')->format('Y-m-d H:i:s'),
        ]);
        $this->em->persist($expiredToken1);

        $expiredToken2 = $this->createRefreshToken([
            'refreshToken' => 'expired-token-444',
            'username' => 'user8@example.com',
            'valid' => new DateTimeImmutable('-1 hour')->format('Y-m-d H:i:s'),
        ]);
        $this->em->persist($expiredToken2);

        $expiredToken3 = $this->createRefreshToken([
            'refreshToken' => 'expired-token-555',
            'username' => 'user9@example.com',
            'valid' => new DateTimeImmutable('-3 days')->format('Y-m-d H:i:s'),
        ]);
        $this->em->persist($expiredToken3);

        $this->em->flush();

        $invalidTokens = $this->repo->findInvalid();

        $this->assertCount(3, $invalidTokens);

        // Cleanup
        $this->em->remove($expiredToken1);
        $this->em->remove($expiredToken2);
        $this->em->remove($expiredToken3);
        $this->em->flush();
    }

    public function testFindInvalidBatchLimitsBatchSize(): void
    {
        $tokens = [];
        for ($i = 1; $i <= 4; ++$i) {
            $token = $this->createRefreshToken([
                'refreshToken' => 'batch-limit-token-' . $i,
                'username' => sprintf('batch-limit-user%d@example.com', $i),
                'valid' => new DateTimeImmutable('-1 day')->format('Y-m-d H:i:s'),
            ]);
            $this->em->persist($token);
            $tokens[] = $token;
        }

        $this->em->flush();

        $result = $this->repo->findInvalidBatch(null, 2);

        $this->assertCount(2, $result);

        foreach ($tokens as $token) {
            $this->em->remove($token);
        }

        $this->em->flush();
    }

    public function testFindInvalidBatchAppliesOffset(): void
    {
        $token1 = $this->createRefreshToken([
            'refreshToken' => 'batch-offset-token-1',
            'username' => 'batch-offset-user1@example.com',
            'valid' => new DateTimeImmutable('-3 days')->format('Y-m-d H:i:s'),
        ]);
        $token2 = $this->createRefreshToken([
            'refreshToken' => 'batch-offset-token-2',
            'username' => 'batch-offset-user2@example.com',
            'valid' => new DateTimeImmutable('-2 days')->format('Y-m-d H:i:s'),
        ]);
        $token3 = $this->createRefreshToken([
            'refreshToken' => 'batch-offset-token-3',
            'username' => 'batch-offset-user3@example.com',
            'valid' => new DateTimeImmutable('-1 day')->format('Y-m-d H:i:s'),
        ]);
        $this->em->persist($token1);
        $this->em->persist($token2);
        $this->em->persist($token3);
        $this->em->flush();

        $all = $this->repo->findInvalidBatch(null, null, 0);
        $withOffset = $this->repo->findInvalidBatch(null, null, 2);

        $this->assertCount(\count($all) - 2, $withOffset);

        $this->em->remove($token1);
        $this->em->remove($token2);
        $this->em->remove($token3);
        $this->em->flush();
    }

    public function testFindInvalidBatchWithBatchSizeAndOffset(): void
    {
        $tokens = [];
        for ($i = 1; $i <= 5; ++$i) {
            $token = $this->createRefreshToken([
                'refreshToken' => 'batch-combo-token-' . $i,
                'username' => sprintf('batch-combo-user%d@example.com', $i),
                'valid' => new DateTimeImmutable('-1 day')->format('Y-m-d H:i:s'),
            ]);
            $this->em->persist($token);
            $tokens[] = $token;
        }

        $this->em->flush();

        $total = $this->repo->findInvalidBatch(null, null, 0);
        $page = $this->repo->findInvalidBatch(null, 2, 2);

        $this->assertCount(2, $page);
        $this->assertLessThanOrEqual(\count($total), \count($page) + 2);

        foreach ($tokens as $token) {
            $this->em->remove($token);
        }

        $this->em->flush();
    }

    public function testFindInvalidBatchWithCustomDate(): void
    {
        $tokenExpiring2Hours = $this->createRefreshToken([
            'refreshToken' => 'batch-date-token-2h',
            'username' => 'batch-date-user1@example.com',
            'valid' => new DateTimeImmutable('+2 hours')->format('Y-m-d H:i:s'),
        ]);
        $tokenExpiring5Hours = $this->createRefreshToken([
            'refreshToken' => 'batch-date-token-5h',
            'username' => 'batch-date-user2@example.com',
            'valid' => new DateTimeImmutable('+5 hours')->format('Y-m-d H:i:s'),
        ]);
        $this->em->persist($tokenExpiring2Hours);
        $this->em->persist($tokenExpiring5Hours);
        $this->em->flush();

        $customDate = new DateTime('+3 hours');
        $result = $this->repo->findInvalidBatch($customDate, null, 0);

        $refreshTokenValues = array_map(
            static fn (RefreshToken $t): ?string => $t->getRefreshToken(),
            $result instanceof \Traversable ? iterator_to_array($result) : $result,
        );

        $this->assertContains('batch-date-token-2h', $refreshTokenValues);
        $this->assertNotContains('batch-date-token-5h', $refreshTokenValues);

        $this->em->remove($tokenExpiring2Hours);
        $this->em->remove($tokenExpiring5Hours);
        $this->em->flush();
    }

    public function testFindInvalidBatchWithNullBatchSizeReturnsAllExpired(): void
    {
        $tokens = [];
        for ($i = 1; $i <= 3; ++$i) {
            $token = $this->createRefreshToken([
                'refreshToken' => 'batch-null-token-' . $i,
                'username' => sprintf('batch-null-user%d@example.com', $i),
                'valid' => new DateTimeImmutable('-1 day')->format('Y-m-d H:i:s'),
            ]);
            $this->em->persist($token);
            $tokens[] = $token;
        }

        $this->em->flush();

        $result = $this->repo->findInvalidBatch(null, null, 0);

        $refreshTokenValues = array_map(
            static fn (RefreshToken $t): ?string => $t->getRefreshToken(),
            $result instanceof \Traversable ? iterator_to_array($result) : $result,
        );

        foreach ($tokens as $token) {
            $this->assertContains($token->getRefreshToken(), $refreshTokenValues);
        }

        foreach ($tokens as $token) {
            $this->em->remove($token);
        }

        $this->em->flush();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->em->close();
    }

    private function createRefreshToken(array $data): RefreshToken
    {
        $token = new RefreshToken();
        $token->setRefreshToken($data['refreshToken']);
        $token->setUsername($data['username']);
        $token->setValid(new DateTime($data['valid']));

        return $token;
    }
}
