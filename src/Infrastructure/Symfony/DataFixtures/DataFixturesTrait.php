<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\DataFixtures;

use App\Infrastructure\Persistence\Doctrine\User\UserEntity as User;
use App\Infrastructure\Symfony\DataFixtures\dev\User\UserFixtures;
use App\Infrastructure\Symfony\DataFixtures\test\User\UserFixtures as UserTestFixtures;
use DateTimeImmutable;
use DateTimeInterface;
use Faker\Factory;
use RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Yaml\Yaml;

trait DataFixturesTrait
{
    public function __construct(
        private readonly ParameterBagInterface $parameter,
    ) {
    }

    public function getUsers(): array
    {
        $users = [];

        for ($i = 1; $i <= UserFixtures::NB_USER; ++$i) {
            $users[] = $this->getReference('user_' . $i, User::class);
        }

        $users[] = $this->getReference('venom', User::class);
        $users[] = $this->getReference('marine', User::class);
        $users[] = $this->getReference('anna', User::class);

        return $users;
    }

    public function getTestUsers(): array
    {
        $users = [];

        for ($i = 1; $i <= UserTestFixtures::NB_TYPE_USER; ++$i) {
            $users[] = $this->getReference('user_admin_' . $i, User::class);
        }

        for ($i = 1; $i <= UserTestFixtures::NB_TYPE_USER; ++$i) {
            $users[] = $this->getReference('user_moder_' . $i, User::class);
        }

        for ($i = 1; $i <= UserTestFixtures::NB_TYPE_USER; ++$i) {
            $users[] = $this->getReference('user_member_' . $i, User::class);
        }

        $users[] = $this->getReference('user_admin', User::class);
        $users[] = $this->getReference('user_moder', User::class);
        $users[] = $this->getReference('user_member', User::class);

        return $users;
    }

    public function generateTimestamps(?DateTimeInterface $createdAt = null): array
    {
        $faker = Factory::create();

        $createdAt = $createdAt ?? $faker->dateTimeBetween('-20 years', '-2 days');
        $updatedAt = $faker->dateTimeBetween($createdAt, '-1 days');

        $createdAtImmutable = $createdAt instanceof DateTimeImmutable ? $createdAt : DateTimeImmutable::createFromMutable($createdAt);
        $updatedAtImmutable = $updatedAt instanceof DateTimeImmutable ? $updatedAt : DateTimeImmutable::createFromMutable($updatedAt);

        return [
            'createdAt' => $createdAtImmutable,
            'updatedAt' => $updatedAtImmutable,
        ];
    }

    public function getDatas(string $path, string $arrayKey): array
    {
        $projectDir = $this->parameter->get('kernel.project_dir');

        if (!is_string($projectDir)) {
            throw new RuntimeException('Project directory path is invalid.');
        }

        return Yaml::parseFile($projectDir . $path)[$arrayKey];
    }
}
