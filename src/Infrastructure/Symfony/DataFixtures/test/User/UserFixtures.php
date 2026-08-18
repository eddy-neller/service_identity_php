<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\DataFixtures\test\User;

use App\Domain\User\ValueObject\Access\RoleSet;
use App\Domain\User\ValueObject\Identity\ActiveEmail;
use App\Domain\User\ValueObject\Lifecycle\UserStatus;
use App\Domain\User\ValueObject\Security\ResetPassword;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity as User;
use App\Infrastructure\Symfony\DataFixtures\DataFixturesTrait;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Ramsey\Uuid\Uuid;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture implements FixtureGroupInterface
{
    use DataFixturesTrait;

    public const int NB_TYPE_USER = 10;

    public const string ACTIVATION_EMAIL = 'user_activation@en-develop.fr';

    public const string ACTIVATION_RAW_TOKEN = 'valid-activation-token-123';

    private const string USER_ID_NAMESPACE = 'd2766be4-a9d4-5a98-a2bb-705ef940e535';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create();

        $usersData = [
            [
                'username' => 'user_admin',
                'password' => 'user_admin',
                'email' => 'user_admin@en-develop.fr',
                'roles' => ['ROLE_ADMIN'],
            ],
            [
                'username' => 'user_moder',
                'password' => 'user_moder',
                'email' => 'user_moder@en-develop.fr',
                'roles' => ['ROLE_MODERATEUR'],
            ],
            [
                'username' => 'user_member',
                'password' => 'user_member',
                'email' => 'user_member@en-develop.fr',
            ],
        ];

        for ($i = 1; $i <= self::NB_TYPE_USER; ++$i) {
            $usersData[] = [
                'username' => 'user_admin_' . $i,
                'password' => 'user_admin_' . $i,
                'email' => 'user_admin_' . $i . '@en-develop.fr',
                'roles' => ['ROLE_ADMIN'],
            ];
        }

        for ($i = 1; $i <= self::NB_TYPE_USER; ++$i) {
            $usersData[] = [
                'username' => 'user_moder_' . $i,
                'password' => 'user_moder_' . $i,
                'email' => 'user_moder_' . $i . '@en-develop.fr',
                'roles' => ['ROLE_MODERATEUR'],
            ];
        }

        for ($i = 1; $i <= self::NB_TYPE_USER; ++$i) {
            $usersData[] = [
                'username' => 'user_member_' . $i,
                'password' => 'user_member_' . $i,
                'email' => 'user_member_' . $i . '@en-develop.fr',
            ];
        }

        foreach ($usersData as $userData) {
            $user = new User();
            $user->firstname = $faker->firstName();
            $user->lastname = $faker->lastName();

            $username = $userData['username'];

            $user->setId(Uuid::fromString(self::userIdOf($username)));
            $user->setUsername($username);
            $hashedPassword = $this->passwordHasher->hashPassword($user, $userData['password']);
            $user->setPassword($hashedPassword);

            $user->setEmail($userData['email']);

            $user->setAvatarName('avatar.png');

            $user->setRoles($userData['roles'] ?? [RoleSet::ROLE_USER]);
            $user->setStatus(UserStatus::ACTIVE);

            $timestamps = $this->generateTimestamps();
            $user->setCreatedAt($timestamps['createdAt']);
            $user->setUpdatedAt($timestamps['updatedAt']);

            $this->addReference($username, $user);

            $manager->persist($user);
        }

        $this->addUserActivation($manager);

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['test'];
    }

    public static function userIdOf(string $username): string
    {
        return Uuid::uuid5(self::USER_ID_NAMESPACE, $username)->toString();
    }

    private function addUserActivation(ObjectManager $manager): void
    {
        $faker = Factory::create();

        $activationUser = new User();
        $activationUser->setId(Uuid::fromString(self::userIdOf('user_activation')));
        $activationUser->firstname = $faker->firstName();
        $activationUser->lastname = $faker->lastName();
        $activationUser->setUsername('user_activation');
        $activationUser->setEmail(self::ACTIVATION_EMAIL);

        $hashed = $this->passwordHasher->hashPassword($activationUser, 'user_activation');
        $activationUser->setPassword($hashed);

        $activationUser->setRoles(['ROLE_USER']);
        $activationUser->setStatus(UserStatus::INACTIVE);

        $activationUser->setAvatarName('avatar.png');

        $timestamps = $this->generateTimestamps();
        $activationUser->setCreatedAt($timestamps['createdAt']);
        $activationUser->setUpdatedAt($timestamps['updatedAt']);

        $activeEmail = ActiveEmail::create(
            mailSent: 0,
            token: self::ACTIVATION_RAW_TOKEN,
            tokenTtl: new DateTimeImmutable('+10 years')->getTimestamp(),
        );

        $activationUser->setActiveEmail($activeEmail);

        $resetPassword = ResetPassword::create(
            mailSent: 0,
            token: self::ACTIVATION_RAW_TOKEN,
            tokenTtl: new DateTimeImmutable('+10 years')->getTimestamp(),
        );

        $activationUser->setResetPassword($resetPassword);

        $this->addReference('user_activation', $activationUser);

        $manager->persist($activationUser);
    }
}
