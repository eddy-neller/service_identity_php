<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Integration\Persistence\User;

use App\Domain\User\Model\User;
use App\Domain\User\ValueObject\Access\RoleSet;
use App\Domain\User\ValueObject\Identity\EmailAddress;
use App\Domain\User\ValueObject\Identity\UserId;
use App\Domain\User\ValueObject\Identity\Username;
use App\Domain\User\ValueObject\Lifecycle\UserStatus;
use App\Domain\User\ValueObject\Profile\Preferences;
use App\Domain\User\ValueObject\Security\HashedPassword;
use App\Infrastructure\Adapter\Uuid\UuidGeneratorInterface;
use App\Infrastructure\Persistence\Doctrine\User\DoctrineUserRepository;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Infrastructure\Persistence\Doctrine\User\UserMapper;
use App\Infrastructure\Persistence\Doctrine\User\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineUserRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private DoctrineUserRepository $repository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();

        /** @var UserRepository $entities */
        $entities = $this->entityManager->getRepository(UserEntity::class);
        $this->repository = new DoctrineUserRepository(
            $this->entityManager,
            $entities,
            new UserMapper(),
            $this->createStub(UuidGeneratorInterface::class),
        );
    }

    public function testFindByIdForUpdateRereadsARowTheEntityManagerAlreadyHolds(): void
    {
        $user = $this->createUser();
        $this->repository->add($user);
        $this->repository->findById($user->getId());

        // Une écriture concurrente, simulée hors de l'identity map.
        $this->entityManager->createQuery('UPDATE ' . UserEntity::class . ' u SET u.nbLogin = 7 WHERE u.id = :id')
            ->setParameter('id', $user->getId()->toString())
            ->execute();

        $this->assertSame(0, $this->repository->findById($user->getId())?->getLoginCount(), 'findById() rend l\'état périmé de l\'identity map : c\'est ce que findByIdForUpdate() corrige.');

        $reread = $this->entityManager->wrapInTransaction(fn (): ?User => $this->repository->findByIdForUpdate($user->getId()));

        $this->assertSame(7, $reread?->getLoginCount());
    }

    public function testFindByIdForUpdateReturnsNullForAnUnknownUser(): void
    {
        $reread = $this->entityManager->wrapInTransaction(
            fn (): ?User => $this->repository->findByIdForUpdate(UserId::fromString('550e8400-e29b-41d4-a716-4466554400ff')),
        );

        $this->assertNull($reread);
    }

    private function createUser(): User
    {
        return User::createByAdmin(
            id: UserId::fromString('550e8400-e29b-41d4-a716-446655440077'),
            username: Username::fromString('lockedreread'),
            email: EmailAddress::fromString('lockedreread@example.com'),
            password: HashedPassword::fromString('hash'),
            roles: RoleSet::fromArray(['ROLE_USER']),
            status: UserStatus::active(),
            now: new DateTimeImmutable('2026-07-18 10:00:00'),
            preferences: Preferences::create(),
        );
    }
}
