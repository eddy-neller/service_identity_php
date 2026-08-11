<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Integration\Persistence\User;

use App\Domain\User\ValueObject\Lifecycle\UserStatus;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity as User;
use App\Infrastructure\Persistence\Doctrine\User\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

final class UserRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    private UserRepository $repo;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->em = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();

        /** @var UserRepository $repo */
        $repo = $this->em->getRepository(User::class);
        $this->repo = $repo;
    }

    public function testSaveAndRemove(): void
    {
        $user = $this->createUser();

        $this->repo->save($user, true);

        $id = $user->getId();
        $this->repo->remove($user, true);
        $this->assertNull($this->repo->find($id));
    }

    public function testUpgradePassword(): void
    {
        $user = $this->createUser([
            'firstname' => 'Jane',
            'lastname' => 'Smith',
            'username' => 'janesmith',
            'email' => 'janesmith@example.com',
            'password' => 'oldpassword',
        ]);
        $this->repo->save($user, true);

        $newPassword = 'newhashedpassword';
        $this->repo->upgradePassword($user, $newPassword);
        $this->em->refresh($user);

        $this->assertSame($newPassword, $user->getPassword());
    }

    public function testUpgradePasswordThrowsException(): void
    {
        $mock = $this->createStub(PasswordAuthenticatedUserInterface::class);

        $this->expectException(UnsupportedUserException::class);

        $this->repo->upgradePassword($mock, 'irrelevant');
    }

    public function testFindInJsonField(): void
    {
        $user = $this->createUser([
            'firstname' => 'Json',
            'lastname' => 'Field',
            'username' => 'jsonfield',
            'email' => 'jsonfield@example.com',
            'preferences' => ['unique_key' => 'unique_value'],
        ]);
        $this->repo->save($user, true);

        $found = $this->repo->findInJsonField('preferences', 'unique_key', 'unique_value');

        $this->assertInstanceOf(User::class, $found);
        $this->assertSame('jsonfield', $found->getUsername());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->em->close();
    }

    private function createUser(array $data = []): User
    {
        $user = new User();
        $user->setId($data['id'] ?? Uuid::uuid4());
        $user->firstname = $data['firstname'] ?? 'John';
        $user->lastname = $data['lastname'] ?? 'Doe';
        $user->setUsername($data['username'] ?? 'johndoe');
        $user->setEmail($data['email'] ?? 'johndoe@example.com');
        $user->setPassword($data['password'] ?? 'password');
        $user->setRoles($data['roles'] ?? ['ROLE_USER']);
        $user->setStatus($data['status'] ?? UserStatus::ACTIVE);

        if (isset($data['preferences'])) {
            $user->setPreferences($data['preferences']);
        }

        return $user;
    }
}
