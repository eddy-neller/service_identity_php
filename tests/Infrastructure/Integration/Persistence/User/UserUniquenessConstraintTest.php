<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Integration\Persistence\User;

use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Domain\User\Exception\Uniqueness\EmailAlreadyUsedException;
use App\Domain\User\Exception\Uniqueness\UsernameAlreadyUsedException;
use App\Domain\User\Model\User;
use App\Domain\User\ValueObject\Identity\EmailAddress;
use App\Domain\User\ValueObject\Identity\Username;
use App\Domain\User\ValueObject\Profile\Preferences;
use App\Domain\User\ValueObject\Security\HashedPassword;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * L'unicité email/username est garantie par un index unique en base, pas par le
 * check-then-insert de UserUniquenessChecker : deux inscriptions concurrentes passent
 * toutes les deux le SELECT en READ COMMITTED. Ce test court-circuite le checker pour
 * vérifier que la contrainte tient et qu'elle ressort en exception métier (409) et non
 * en UniqueConstraintViolationException (500).
 */
final class UserUniquenessConstraintTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    private UserRepositoryInterface $repository;

    private TransactionalInterface $transactional;

    private DebugDataHolder $queries;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();
        $this->em = $em;

        /** @var UserRepositoryInterface $repository */
        $repository = $container->get(UserRepositoryInterface::class);
        $this->repository = $repository;

        /** @var TransactionalInterface $transactional */
        $transactional = $container->get(TransactionalInterface::class);
        $this->transactional = $transactional;

        /** @var DebugDataHolder $queries */
        $queries = $container->get('doctrine.debug_data_holder');
        $this->queries = $queries;
    }

    public function testDuplicateEmailIsRejectedAsDomainConflict(): void
    {
        $this->repository->add($this->createUser('dupemail', 'dup-email@example.com'));

        $this->expectException(EmailAlreadyUsedException::class);

        $this->repository->add($this->createUser('otheruser', 'dup-email@example.com'));
    }

    public function testDuplicateUsernameIsRejectedAsDomainConflict(): void
    {
        $this->repository->add($this->createUser('dupusername', 'first@example.com'));

        $this->expectException(UsernameAlreadyUsedException::class);

        $this->repository->add($this->createUser('dupusername', 'second@example.com'));
    }

    /**
     * DoctrineTransactional ferme l'EntityManager puis rollback quand la closure lève.
     * On vérifie que ce chemin ne masque pas l'exception métier derrière un
     * « EntityManager is closed » — sinon le conflit ressortirait en 500.
     */
    public function testConflictInsideTransactionSurfacesAsDomainException(): void
    {
        $this->repository->add($this->createUser('txfirst', 'tx-dup@example.com'));

        $this->expectException(EmailAlreadyUsedException::class);

        $this->transactional->transactional(function (): void {
            $this->repository->add($this->createUser('txsecond', 'tx-dup@example.com'));
        });
    }

    /**
     * add() cible un id neuf : aucun SELECT préalable ne doit être émis.
     */
    public function testAddDoesNotIssueALookupBeforeInsert(): void
    {
        $this->queries->reset();

        $this->repository->add($this->createUser('nolookup', 'no-lookup@example.com'));

        $selects = array_values(array_filter(
            array_column($this->queries->getData()['default'] ?? [], 'sql'),
            static fn (string $sql): bool => str_starts_with(strtoupper(ltrim($sql)), 'SELECT'),
        ));

        $this->assertSame(
            [],
            $selects,
            "add() ne doit émettre aucun SELECT. Requêtes parasites :\n" . implode("\n", $selects),
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->em->close();
    }

    private function createUser(string $username, string $email): User
    {
        return User::register(
            id: $this->repository->nextIdentity(),
            username: Username::fromString($username),
            email: EmailAddress::fromString($email),
            password: HashedPassword::fromString('$2y$13$abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTU'),
            preferences: Preferences::fromArray([]),
            now: new DateTimeImmutable('2026-08-08 12:00:00'),
        );
    }
}
