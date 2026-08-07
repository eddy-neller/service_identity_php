<?php

declare(strict_types=1);

namespace App\Infrastructure\Tests\Integration\Persistence\User;

use App\Domain\User\Model\RefreshToken;
use App\Domain\User\ValueObject\Identity\UserId;
use App\Domain\User\ValueObject\Security\RefreshTokenHash;
use App\Domain\User\ValueObject\Security\RefreshTokenId;
use App\Infrastructure\Entity\User\RefreshToken as DoctrineRefreshToken;
use App\Infrastructure\Persistence\Doctrine\User\DoctrineRefreshTokenRepository;
use App\Infrastructure\Persistence\Doctrine\User\RefreshTokenMapper;
use App\Infrastructure\Service\Uuid\UuidGeneratorInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineRefreshTokenRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private DoctrineRefreshTokenRepository $repository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
        $this->repository = new DoctrineRefreshTokenRepository(
            $this->entityManager,
            new RefreshTokenMapper(),
            $this->createStub(UuidGeneratorInterface::class),
        );
    }

    public function testDeleteAllForUserRemovesOnlyThatUsersRefreshTokens(): void
    {
        $firstUserId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $secondUserId = UserId::fromString('550e8400-e29b-41d4-a716-446655440001');

        $this->repository->save($this->createRefreshToken(
            '550e8400-e29b-41d4-a716-446655440010',
            $firstUserId,
            'first-user-token-one',
        ));
        $this->repository->save($this->createRefreshToken(
            '550e8400-e29b-41d4-a716-446655440011',
            $firstUserId,
            'first-user-token-two',
        ));
        $this->repository->save($this->createRefreshToken(
            '550e8400-e29b-41d4-a716-446655440012',
            $secondUserId,
            'second-user-token',
        ));

        $this->repository->deleteAllForUser($firstUserId);

        $entityRepository = $this->entityManager->getRepository(DoctrineRefreshToken::class);
        $this->assertSame(0, $entityRepository->count(['userId' => $firstUserId->toString()]));
        $this->assertSame(1, $entityRepository->count(['userId' => $secondUserId->toString()]));
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->entityManager->close();
    }

    private function createRefreshToken(string $id, UserId $userId, string $hash): RefreshToken
    {
        $now = new DateTimeImmutable('2025-01-01 10:00:00');

        return RefreshToken::issue(
            id: RefreshTokenId::fromString($id),
            userId: $userId,
            hash: RefreshTokenHash::fromString($hash),
            expiresAt: $now->modify('+1 hour'),
            now: $now,
        );
    }
}
