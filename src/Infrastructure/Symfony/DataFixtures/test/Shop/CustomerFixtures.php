<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\DataFixtures\test\Shop;

use App\Domain\Shop\Customer\ValueObject\CustomerStatus;
use App\Infrastructure\Persistence\Doctrine\Shop\Customer\CustomerEntity as Customer;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity as User;
use App\Infrastructure\Symfony\DataFixtures\DataFixturesTrait;
use App\Infrastructure\Symfony\DataFixtures\test\User\UserFixtures;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Ramsey\Uuid\Uuid;

final class CustomerFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    use DataFixturesTrait;

    public function load(ObjectManager $manager): void
    {
        /** @var User $userMember */
        $userMember = $this->getReference('user_member', User::class);

        /** @var User $userAdmin */
        $userAdmin = $this->getReference('user_admin', User::class);

        $this->createCustomer($manager, 'customer_member', $userMember);
        $this->createCustomer($manager, 'customer_admin', $userAdmin);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['test'];
    }

    private function createCustomer(ObjectManager $manager, string $reference, User $user): void
    {
        $customer = new Customer();
        $customer->setId(Uuid::uuid4());
        $customer->setUserAccountId(Uuid::fromString($user->getId()->toString()));
        $customer->setStatus(CustomerStatus::ACTIVE);

        $timestamps = $this->generateTimestamps();
        $customer->setCreatedAt($timestamps['createdAt']);
        $customer->setUpdatedAt($timestamps['updatedAt']);

        $this->addReference($reference, $customer);

        $manager->persist($customer);
    }
}
