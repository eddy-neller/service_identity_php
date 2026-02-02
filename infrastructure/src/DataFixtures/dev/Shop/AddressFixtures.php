<?php

declare(strict_types=1);

namespace App\Infrastructure\DataFixtures\dev\Shop;

use App\Infrastructure\DataFixtures\DataFixturesTrait;
use App\Infrastructure\Entity\Shop\Address;
use App\Infrastructure\Entity\Shop\Customer;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Ramsey\Uuid\Uuid;

final class AddressFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    use DataFixturesTrait;

    public function load(ObjectManager $manager): void
    {
        /** @var Customer $customerVenom */
        $customerVenom = $this->getReference('customer_venom', Customer::class);

        /** @var Customer $customerMarine */
        $customerMarine = $this->getReference('customer_marine', Customer::class);

        /** @var Customer $customerAnna */
        $customerAnna = $this->getReference('customer_anna', Customer::class);

        $this->createAddress($manager, $customerVenom, [
            'name' => 'Maison',
            'firstname' => 'Eddy',
            'lastname' => 'Neller',
            'company' => 'EN Develop',
            'address' => '10 rue de la Paix',
            'zip' => '75001',
            'city' => 'Paris',
            'country' => 'France',
            'phone' => '+33 1 23 45 67 89',
        ]);

        $this->createAddress($manager, $customerMarine, [
            'name' => 'Bureau',
            'firstname' => 'Marine',
            'lastname' => 'Durand',
            'company' => null,
            'address' => '25 avenue Victor Hugo',
            'zip' => '69001',
            'city' => 'Lyon',
            'country' => 'France',
            'phone' => '+33 4 12 34 56 78',
        ]);

        $this->createAddress($manager, $customerAnna, [
            'name' => 'Appartement',
            'firstname' => 'Anna',
            'lastname' => 'Martin',
            'company' => null,
            'address' => '5 boulevard des Alpes',
            'zip' => '38000',
            'city' => 'Grenoble',
            'country' => 'France',
            'phone' => '+33 6 98 76 54 32',
        ]);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            CustomerFixtures::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['dev'];
    }

    private function createAddress(ObjectManager $manager, Customer $customer, array $data): void
    {
        $address = new Address();
        $address->setId(Uuid::uuid4());
        $address->setName($data['name']);
        $address->setFirstname($data['firstname']);
        $address->setLastname($data['lastname']);
        $address->setCompany($data['company']);
        $address->setAddress($data['address']);
        $address->setZip($data['zip']);
        $address->setCity($data['city']);
        $address->setCountry($data['country']);
        $address->setPhone($data['phone']);
        $address->setCustomer($customer);

        $timestamps = $this->generateTimestamps();
        $address->setCreatedAt($timestamps['createdAt']);
        $address->setUpdatedAt($timestamps['updatedAt']);

        $manager->persist($address);
    }
}
