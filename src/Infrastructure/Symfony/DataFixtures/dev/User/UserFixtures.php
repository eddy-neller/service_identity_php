<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\DataFixtures\dev\User;

use App\Domain\User\ValueObject\Access\RoleSet;
use App\Domain\User\ValueObject\Lifecycle\UserStatus;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity as User;
use App\Infrastructure\Symfony\DataFixtures\DataFixturesTrait;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Ramsey\Uuid\Uuid;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture implements FixtureGroupInterface
{
    use DataFixturesTrait;

    public const int NB_USER = 30;

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create();

        $usersData = [
            [
                'firstname' => 'Eddy',
                'lastname' => 'Neller',
                'username' => 'venom',
                'password' => 'userVenom1@',
                'email' => 'venom@en-develop.fr',
                'avatarName' => 'mxqvlpbpkx5gn3hm9r4e6pyo740ik4jj.jpg',
                'roles' => ['ROLE_ADMIN'],
            ],
            [
                'firstname' => 'Marine',
                'username' => 'marine',
                'password' => 'user_marine',
                'email' => 'marine@en-develop.fr',
                'roles' => ['ROLE_MODERATEUR'],
            ],
            [
                'firstname' => 'Anna',
                'username' => 'anna',
                'password' => 'user_anna',
                'email' => 'anna@en-develop.fr',
            ],
        ];

        for ($i = 1; $i <= self::NB_USER; ++$i) {
            $usersData[] = [
                'username' => 'user_' . $i,
                'password' => 'user_' . $i,
            ];
        }

        foreach ($usersData as $userData) {
            $user = new User();
            $user->setId(Uuid::uuid4());
            $user->firstname = $userData['firstname'] ?? $faker->firstName();
            $user->lastname = $userData['lastname'] ?? $faker->lastName();

            $username = $userData['username'];

            $user->setUsername($username);
            $hashedPassword = $this->passwordHasher->hashPassword($user, $userData['password']);
            $user->setPassword($hashedPassword);

            $user->setEmail($userData['email'] ?? $faker->unique()->safeEmail());

            $user->setAvatarName($userData['avatarName'] ?? null);

            $user->setRoles($userData['roles'] ?? [RoleSet::ROLE_USER]);
            $user->setStatus(UserStatus::ACTIVE);

            $timestamps = $this->generateTimestamps();
            $user->setCreatedAt($timestamps['createdAt']);
            $user->setUpdatedAt($timestamps['updatedAt']);

            $this->addReference($username, $user);

            $manager->persist($user);
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['dev'];
    }
}
