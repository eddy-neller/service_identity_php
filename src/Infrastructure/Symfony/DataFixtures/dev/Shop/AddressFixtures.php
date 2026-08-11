<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\DataFixtures\dev\Shop;

use App\Infrastructure\Persistence\Doctrine\Shop\Customer\AddressEntity as Address;
use App\Infrastructure\Persistence\Doctrine\Shop\Customer\CustomerEntity as Customer;
use App\Infrastructure\Symfony\DataFixtures\DataFixturesTrait;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Ramsey\Uuid\Uuid;

final class AddressFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    use DataFixturesTrait;

    private const array ADDRESSES = [
        'customer_venom' => [
            ['name' => 'Maison', 'firstname' => 'Eddy', 'lastname' => 'Neller', 'company' => 'EN Develop', 'address' => '10 rue de la Paix', 'zip' => '75001', 'city' => 'Paris', 'country' => 'France', 'phone' => '+33 1 23 45 67 89'],
            ['name' => 'Bureau', 'firstname' => 'Eddy', 'lastname' => 'Neller', 'company' => 'EN Develop', 'address' => '42 avenue des Champs-Élysées', 'zip' => '75008', 'city' => 'Paris', 'country' => 'France', 'phone' => '+33 1 98 76 54 32'],
            ['name' => 'Parents', 'firstname' => 'Eddy', 'lastname' => 'Neller', 'company' => null, 'address' => '3 impasse des Lilas', 'zip' => '59000', 'city' => 'Lille', 'country' => 'France', 'phone' => '+33 3 20 11 22 33'],
        ],
        'customer_marine' => [
            ['name' => 'Bureau', 'firstname' => 'Marine', 'lastname' => 'Durand', 'company' => null, 'address' => '25 avenue Victor Hugo', 'zip' => '69001', 'city' => 'Lyon', 'country' => 'France', 'phone' => '+33 4 12 34 56 78'],
            ['name' => 'Domicile', 'firstname' => 'Marine', 'lastname' => 'Durand', 'company' => null, 'address' => '8 rue Bellecour', 'zip' => '69002', 'city' => 'Lyon', 'country' => 'France', 'phone' => '+33 6 11 22 33 44'],
            ['name' => 'Famille', 'firstname' => 'Marine', 'lastname' => 'Durand', 'company' => null, 'address' => '17 rue du Rhône', 'zip' => '01000', 'city' => 'Bourg-en-Bresse', 'country' => 'France', 'phone' => '+33 4 74 55 66 77'],
        ],
        'customer_anna' => [
            ['name' => 'Appartement', 'firstname' => 'Anna', 'lastname' => 'Martin', 'company' => null, 'address' => '5 boulevard des Alpes', 'zip' => '38000', 'city' => 'Grenoble', 'country' => 'France', 'phone' => '+33 6 98 76 54 32'],
            ['name' => 'Travail', 'firstname' => 'Anna', 'lastname' => 'Martin', 'company' => 'Schneider Electric', 'address' => '35 rue Joseph Monier', 'zip' => '92500', 'city' => 'Rueil-Malmaison', 'country' => 'France', 'phone' => '+33 1 41 29 70 00'],
            ['name' => 'Résidence secondaire', 'firstname' => 'Anna', 'lastname' => 'Martin', 'company' => null, 'address' => '12 chemin du Vercors', 'zip' => '38250', 'city' => 'Villard-de-Lans', 'country' => 'France', 'phone' => '+33 4 76 95 10 38'],
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::ADDRESSES as $reference => $addresses) {
            /** @var Customer $customer */
            $customer = $this->getReference($reference, Customer::class);

            foreach ($addresses as $index => $data) {
                $this->createAddress($manager, $customer, $data, 0 === $index);
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [CustomerFixtures::class];
    }

    public static function getGroups(): array
    {
        return ['dev'];
    }

    private function createAddress(ObjectManager $manager, Customer $customer, array $data, bool $isDefault): void
    {
        $address = new Address();
        $address->setId(Uuid::uuid4());
        $address->setCustomer($customer);
        $address->setName($data['name']);
        $address->setFirstname($data['firstname']);
        $address->setLastname($data['lastname']);
        $address->setCompany($data['company']);
        $address->setAddress($data['address']);
        $address->setZip($data['zip']);
        $address->setCity($data['city']);
        $address->setCountry($data['country']);
        $address->setPhone($data['phone']);
        $address->setIsDefault($isDefault);

        $timestamps = $this->generateTimestamps();
        $address->setCreatedAt($timestamps['createdAt']);
        $address->setUpdatedAt($timestamps['updatedAt']);

        $manager->persist($address);
    }
}
