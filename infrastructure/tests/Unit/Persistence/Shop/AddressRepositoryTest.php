<?php

declare(strict_types=1);

namespace App\Infrastructure\Tests\Unit\Persistence\Shop;

use App\Application\Shop\Port\AddressRepositoryInterface;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Infrastructure\Entity\Shop\Address;
use App\Infrastructure\Entity\Shop\Customer;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AddressRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    private AddressRepositoryInterface $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get('doctrine')->getManager();
        $this->repository = $container->get(AddressRepositoryInterface::class);
    }

    public function testCountByOwnerForUpdateOnlyCountsRequestedCustomerAddresses(): void
    {
        $customers = $this->em->getRepository(Customer::class)->findAll();

        $this->assertCount(2, $customers);
        [$firstCustomer, $secondCustomer] = $customers;

        $firstCountBefore = $this->countPersistedAddresses($firstCustomer);
        $secondCountBefore = $this->countPersistedAddresses($secondCustomer);

        $this->em->persist($this->createAddress($secondCustomer));
        $this->em->flush();

        $this->em->beginTransaction();
        try {
            $firstCount = $this->repository->countByOwnerForUpdate(
                CustomerId::fromString($firstCustomer->getId()->toString()),
            );
            $secondCount = $this->repository->countByOwnerForUpdate(
                CustomerId::fromString($secondCustomer->getId()->toString()),
            );
        } finally {
            $this->em->rollback();
        }

        $this->assertSame($firstCountBefore, $firstCount);
        $this->assertSame($secondCountBefore + 1, $secondCount);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->em->close();
    }

    private function countPersistedAddresses(Customer $customer): int
    {
        return $this->em->getRepository(Address::class)->count(['customer' => $customer]);
    }

    private function createAddress(Customer $customer): Address
    {
        $now = new DateTimeImmutable('2026-01-01 10:00:00');
        $address = new Address();
        $address->setId(Uuid::uuid4());
        $address->setCustomer($customer);
        $address->setName('Additional address');
        $address->setFirstname('John');
        $address->setLastname('Doe');
        $address->setCompany(null);
        $address->setAddress('12 Main St');
        $address->setZip('75001');
        $address->setCity('Paris');
        $address->setCountry('France');
        $address->setPhone('+33 1 23 45 67 89');
        $address->setIsDefault(false);
        $address->setCreatedAt($now);
        $address->setUpdatedAt($now);

        return $address;
    }
}
