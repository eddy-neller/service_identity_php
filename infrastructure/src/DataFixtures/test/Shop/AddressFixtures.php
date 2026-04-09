<?php

declare(strict_types=1);

namespace App\Infrastructure\DataFixtures\test\Shop;

use App\Infrastructure\DataFixtures\DataFixturesTrait;
use App\Infrastructure\Entity\Shop\Address;
use App\Infrastructure\Entity\Shop\Customer;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Ramsey\Uuid\Uuid;

class AddressFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    use DataFixturesTrait;

    public function load(ObjectManager $manager): void
    {
        /** @var Customer $customer */
        $customer = $this->getReference('customer_member', Customer::class);

        $addressesData = [
            [
                'name' => 'Address name 1',
                'firstname' => 'Jean',
                'lastname' => 'Dupont',
                'company' => 'ACME Corp',
                'address' => '123 rue de la Paix',
                'zip' => '75001',
                'city' => 'Paris',
                'country' => 'France',
                'phone' => '+33 1 23 45 67 89',
            ],
            [
                'name' => 'Address name 2',
                'firstname' => 'Marie',
                'lastname' => 'Martin',
                'company' => null,
                'address' => '45 avenue des Champs',
                'zip' => '69001',
                'city' => 'Lyon',
                'country' => 'France',
                'phone' => '+33 4 12 34 56 78',
            ],
        ];

        foreach ($addressesData as $addressData) {
            $address = new Address();
            $address->setId(Uuid::uuid4());
            $address->setName($addressData['name']);
            $address->setFirstname($addressData['firstname']);
            $address->setLastname($addressData['lastname']);
            $address->setCompany($addressData['company']);
            $address->setAddress($addressData['address']);
            $address->setZip($addressData['zip']);
            $address->setCity($addressData['city']);
            $address->setCountry($addressData['country']);
            $address->setPhone($addressData['phone']);
            $address->setCustomer($customer);

            $timestamps = $this->generateTimestamps();
            $address->setCreatedAt($timestamps['createdAt']);
            $address->setUpdatedAt($timestamps['updatedAt']);

            $manager->persist($address);
        }

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
        return ['test'];
    }
}
