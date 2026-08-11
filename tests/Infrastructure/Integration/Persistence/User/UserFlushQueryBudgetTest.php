<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Integration\Persistence\User;

use App\Domain\User\ValueObject\Lifecycle\UserStatus;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity as User;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Garde-fou : écrire un User ne doit déclencher aucune lecture.
 *
 * Ce test n'a de valeur que parce que `PurgeHttpCacheListener` est actif en test
 * (bloc `when@test` de config/packages/api_platform.yaml). C'est lui qui parcourt
 * les associations de chaque entité modifiée au flush et qui, via un OneToMany
 * inverse inutilisé, chargeait l'intégralité des commandes du client à chaque login.
 */
final class UserFlushQueryBudgetTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    private DebugDataHolder $queries;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();
        $this->em = $em;

        /** @var DebugDataHolder $queries */
        $queries = $container->get('doctrine.debug_data_holder');
        $this->queries = $queries;
    }

    public function testFlushingAUserTriggersNoRead(): void
    {
        $user = $this->createUser();
        $this->em->persist($user);
        $this->em->flush();

        // Indispensable : sur une entité fraîchement créée les collections sont déjà
        // initialisées (vides). Il faut la recharger pour retrouver des associations
        // lazy — l'état réel d'un User chargé par email au login.
        $id = $user->getId();
        $this->em->clear();

        $user = $this->em->getRepository(User::class)->find($id);
        $this->assertNotNull($user);

        $this->queries->reset();

        $user->setNbLogin($user->getNbLogin() + 1);
        $this->em->flush();

        $selects = array_values(array_filter(
            array_column($this->queries->getData()['default'] ?? [], 'sql'),
            static fn (string $sql): bool => str_starts_with(strtoupper(ltrim($sql)), 'SELECT'),
        ));

        $this->assertSame(
            [],
            $selects,
            "Écrire un User ne doit déclencher aucun SELECT. Requêtes parasites :\n"
                . implode("\n", $selects),
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->em->close();
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setId(Uuid::uuid4());
        $user->firstname = 'Query';
        $user->lastname = 'Budget';
        $user->setUsername('querybudget');
        $user->setEmail('querybudget@example.com');
        $user->setPassword('password');
        $user->setRoles(['ROLE_USER']);
        $user->setStatus(UserStatus::ACTIVE);

        return $user;
    }
}
