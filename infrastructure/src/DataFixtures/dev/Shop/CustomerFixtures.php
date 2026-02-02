<?php

declare(strict_types=1);

namespace App\Infrastructure\DataFixtures\dev\Shop;

use App\Domain\Shop\Customer\ValueObject\CustomerStatus;
use App\Infrastructure\DataFixtures\DataFixturesTrait;
use App\Infrastructure\DataFixtures\dev\User\UserFixtures;
use App\Infrastructure\Entity\Shop\Customer;
use App\Infrastructure\Entity\User\User;
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
        /** @var User $venom */
        $venom = $this->getReference('venom', User::class);

        /** @var User $marine */
        $marine = $this->getReference('marine', User::class);

        /** @var User $anna */
        $anna = $this->getReference('anna', User::class);

        $this->createCustomer($manager, 'customer_venom', $venom);
        $this->createCustomer($manager, 'customer_marine', $marine);
        $this->createCustomer($manager, 'customer_anna', $anna);

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
        return ['dev'];
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
