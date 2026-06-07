<?php

declare(strict_types=1);

namespace App\Presentation\Tests\Api\Shop;

use App\Infrastructure\Entity\Shop\Address;
use App\Infrastructure\Entity\Shop\Customer;
use App\Infrastructure\Entity\User\User;
use App\Presentation\Tests\Api\BaseTest;
use DateTimeImmutable;
use Faker\Factory;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;

final class AddressTest extends BaseTest
{
    protected const string URL_API_OPE = self::URL_API . 'shop/me/addresses';

    public const array CRITERIA_IRI = ['name' => 'Address name 1'];

    protected ?string $iri;

    protected function setUp(): void
    {
        parent::setUp();

        $this->iri = $this->findIriByHttp(self::URL_API_OPE, self::CRITERIA_IRI, $this->userMember);
    }

    public static function provideColShopAddress(): Generator
    {
        $memberToken = self::PLACEHOLDERS['TOKENS']['MEMBER'];

        $assertions = [
            BaseTest::ASSERTION_TYPE['SERIALIZATION'] => [
                'hasKey' => [
                    'id',
                    'name',
                    'firstname',
                    'lastname',
                    'address',
                    'zip',
                    'city',
                    'country',
                    'phone',
                    'isDefault',
                    'createdAt',
                    'updatedAt',
                ],
            ],
        ];

        yield 'Member: Normal' => [
            [
                'auth_bearer' => $memberToken,
            ],
            $assertions,
        ];
    }

    #[DataProvider('provideColShopAddress')]
    public function testColShopAddress(
        array $options,
        array $asserts,
    ): void {
        $this->testSuccess(
            Request::METHOD_GET,
            self::URL_API_OPE,
            $options,
            Response::HTTP_OK,
            $asserts,
        );
    }

    public static function provideColShopAddressException(): Generator
    {
        yield 'No role' => [
            [],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'HTTP 401 returned',
            ],
        ];
    }

    #[DataProvider('provideColShopAddressException')]
    public function testColShopAddressException(
        array $options,
        array $exception,
    ): void {
        $this->testException(Request::METHOD_GET, self::URL_API_OPE, $options, $exception);
    }

    public static function provideCreateShopAddressSuccess(): Generator
    {
        $fakeData = self::getFakeDataShopAddress();
        $memberToken = self::PLACEHOLDERS['TOKENS']['MEMBER'];

        $assertSerialization = [
            'hasKey' => [
                'id',
                'name',
                'firstname',
                'lastname',
                'address',
                'zip',
                'city',
                'country',
                'phone',
                'isDefault',
                'createdAt',
                'updatedAt',
            ],
        ];

        yield 'Full' => [
            [
                'auth_bearer' => $memberToken,
                'json' => [
                    'name' => $fakeData['name'],
                    'firstname' => $fakeData['firstname'],
                    'lastname' => $fakeData['lastname'],
                    'company' => $fakeData['company'],
                    'address' => $fakeData['address'],
                    'zip' => $fakeData['zip'],
                    'city' => $fakeData['city'],
                    'country' => $fakeData['country'],
                    'phone' => $fakeData['phone'],
                ],
            ],
            [
                BaseTest::ASSERTION_TYPE['SERIALIZATION'] => $assertSerialization,
                BaseTest::ASSERTION_TYPE['EQUAL'] => [
                    'name' => $fakeData['name'],
                ],
            ],
        ];

        yield 'Without company' => [
            [
                'auth_bearer' => $memberToken,
                'json' => [
                    'name' => $fakeData['name'],
                    'firstname' => $fakeData['firstname'],
                    'lastname' => $fakeData['lastname'],
                    'address' => $fakeData['address'],
                    'zip' => $fakeData['zip'],
                    'city' => $fakeData['city'],
                    'country' => $fakeData['country'],
                    'phone' => $fakeData['phone'],
                ],
            ],
            [
                BaseTest::ASSERTION_TYPE['SERIALIZATION'] => $assertSerialization,
                BaseTest::ASSERTION_TYPE['EQUAL'] => [
                    'name' => $fakeData['name'],
                ],
            ],
        ];
    }

    #[DataProvider('provideCreateShopAddressSuccess')]
    public function testCreateShopAddressSuccess(
        array $options,
        array $asserts,
    ): void {
        $this->testSuccess(
            Request::METHOD_POST,
            self::URL_API_OPE,
            $options,
            Response::HTTP_CREATED,
            $asserts,
        );
    }

    public static function provideCreateAddressException(): Generator
    {
        $memberToken = self::PLACEHOLDERS['TOKENS']['MEMBER'];

        yield 'Empty' => [
            [
                'auth_bearer' => $memberToken,
                'json' => [],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'name: This value should not be blank.
firstname: This value should not be blank.
lastname: This value should not be blank.
address: This value should not be blank.
zip: This value should not be blank.
city: This value should not be blank.
country: This value should not be blank.
phone: This value should not be blank.',
            ],
        ];

        yield 'No role' => [
            [
                'json' => [
                    'name' => 'Test Address',
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'HTTP 401 returned',
            ],
        ];
    }

    #[DataProvider('provideCreateAddressException')]
    public function testCreateAddressException(
        array $options,
        array $exception,
    ): void {
        $this->testException(Request::METHOD_POST, self::URL_API_OPE, $options, $exception);
    }

    public function testCreateShopAddressReturnsConflictWhenCustomerAlreadyHasFiveAddresses(): void
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $this->userMember . '@en-develop.fr']);
        $this->assertInstanceOf(User::class, $user);

        $customer = $this->em->getRepository(Customer::class)->findOneBy(['userAccountId' => $user->getId()]);
        $this->assertInstanceOf(Customer::class, $customer);

        $addressRepository = $this->em->getRepository(Address::class);
        $addressCount = $addressRepository->count(['customer' => $customer]);
        $this->assertLessThanOrEqual(5, $addressCount);

        for ($index = $addressCount; $index < 5; ++$index) {
            $this->em->persist($this->createPersistedAddress($customer, $index));
        }

        $this->em->flush();

        $fakeData = self::getFakeDataShopAddress();
        $response = $this->client->request(Request::METHOD_POST, self::URL_API_OPE, [
            'auth_bearer' => $this->getToken($this->userMember),
            'headers' => [
                'Accept' => 'application/json',
            ],
            'json' => [
                'name' => $fakeData['name'],
                'firstname' => $fakeData['firstname'],
                'lastname' => $fakeData['lastname'],
                'company' => $fakeData['company'],
                'address' => $fakeData['address'],
                'zip' => $fakeData['zip'],
                'city' => $fakeData['city'],
                'country' => $fakeData['country'],
                'phone' => $fakeData['phone'],
            ],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertStringContainsString(
            'A customer cannot have more than 5 addresses.',
            $response->getContent(false),
        );
        $this->assertSame(5, $addressRepository->count(['customer' => $customer]));
    }

    public function testGetShopAddress(): void
    {
        $assertSerialization = [
            'hasKey' => [
                'id',
                'name',
                'firstname',
                'lastname',
                'address',
                'zip',
                'city',
                'country',
                'phone',
                'isDefault',
                'createdAt',
                'updatedAt',
            ],
        ];

        $this->testSuccess(
            Request::METHOD_GET,
            $this->iri,
            [
                'auth_bearer' => $this->getToken($this->userMember),
            ],
            Response::HTTP_OK,
            [
                BaseTest::ASSERTION_TYPE['SERIALIZATION'] => $assertSerialization,
            ],
        );
    }

    private function createPersistedAddress(Customer $customer, int $index): Address
    {
        $now = new DateTimeImmutable('2026-01-01 10:00:00');
        $address = new Address();
        $address->setId(Uuid::uuid4());
        $address->setCustomer($customer);
        $address->setName('Limit address ' . $index);
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

    public static function provideUpdateShopAddressSuccess(): Generator
    {
        $fakeData = self::getFakeDataShopAddress();
        $memberToken = self::PLACEHOLDERS['TOKENS']['MEMBER'];

        $assertSerialization = [
            'hasKey' => [
                'id',
                'name',
                'firstname',
                'lastname',
                'address',
                'zip',
                'city',
                'country',
                'phone',
                'isDefault',
                'createdAt',
                'updatedAt',
            ],
        ];

        yield 'Full' => [
            [
                'auth_bearer' => $memberToken,
                'headers' => [
                    'Content-Type' => 'application/merge-patch+json',
                ],
                'json' => [
                    'name' => $fakeData['name'],
                    'firstname' => $fakeData['firstname'],
                    'lastname' => $fakeData['lastname'],
                    'city' => $fakeData['city'],
                    'country' => $fakeData['country'],
                ],
            ],
            [
                BaseTest::ASSERTION_TYPE['SERIALIZATION'] => $assertSerialization,
                BaseTest::ASSERTION_TYPE['EQUAL'] => [
                    'name' => $fakeData['name'],
                ],
            ],
        ];

        yield 'Partial: name only' => [
            [
                'auth_bearer' => $memberToken,
                'headers' => [
                    'Content-Type' => 'application/merge-patch+json',
                ],
                'json' => [
                    'name' => $fakeData['name'],
                ],
            ],
            [
                BaseTest::ASSERTION_TYPE['SERIALIZATION'] => $assertSerialization,
                BaseTest::ASSERTION_TYPE['EQUAL'] => [
                    'name' => $fakeData['name'],
                ],
            ],
        ];
    }

    #[DataProvider('provideUpdateShopAddressSuccess')]
    public function testUpdateShopAddressSuccess(
        array $options,
        array $asserts,
    ): void {
        $this->testSuccess(
            Request::METHOD_PATCH,
            $this->iri,
            $options,
            Response::HTTP_OK,
            $asserts,
        );
    }

    public function testSetDefaultShopAddressSuccess(): void
    {
        $this->testSuccess(
            Request::METHOD_POST,
            $this->iri . '/default',
            [
                'auth_bearer' => $this->getToken($this->userMember),
            ],
            Response::HTTP_OK,
            [
                BaseTest::ASSERTION_TYPE['SERIALIZATION'] => [
                    'hasKey' => [
                        'id',
                        'name',
                        'firstname',
                        'lastname',
                        'address',
                        'zip',
                        'city',
                        'country',
                        'phone',
                        'isDefault',
                        'createdAt',
                        'updatedAt',
                    ],
                ],
                BaseTest::ASSERTION_TYPE['EQUAL'] => [
                    'isDefault' => true,
                ],
            ],
        );
    }

    public static function provideUpdateAddressException(): Generator
    {
        $notOwnerToken = self::PLACEHOLDERS['TOKENS']['MEMBER_1'];

        yield 'No role' => [
            [
                'headers' => [
                    'Content-Type' => 'application/merge-patch+json',
                ],
                'json' => [
                    'name' => 'Updated name',
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'HTTP 401 returned',
            ],
        ];

        yield 'Not owner' => [
            [
                'auth_bearer' => $notOwnerToken,
                'headers' => [
                    'Content-Type' => 'application/merge-patch+json',
                ],
                'json' => [
                    'name' => 'Updated name',
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_FORBIDDEN,
                'message' => 'Access Denied',
            ],
        ];
    }

    #[DataProvider('provideUpdateAddressException')]
    public function testUpdateAddressException(
        array $options,
        array $exception,
    ): void {
        $this->testException(Request::METHOD_PATCH, $this->iri, $options, $exception);
    }

    public static function provideDeleteShopAddressSuccess(): Generator
    {
        $ownerToken = self::PLACEHOLDERS['TOKENS']['MEMBER'];

        yield 'Full: Owner' => [
            [
                'auth_bearer' => $ownerToken,
            ],
        ];
    }

    #[DataProvider('provideDeleteShopAddressSuccess')]
    public function testDeleteShopAddressSuccess(
        array $options,
    ): void {
        $this->testSuccess(
            Request::METHOD_DELETE,
            $this->iri,
            $options,
            Response::HTTP_NO_CONTENT,
        );
    }

    public static function provideDeleteShopAddressException(): Generator
    {
        $notOwnerToken = self::PLACEHOLDERS['TOKENS']['MEMBER_1'];

        yield 'No role' => [
            [],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'HTTP 401 returned',
            ],
        ];

        yield 'Not owner' => [
            [
                'auth_bearer' => $notOwnerToken,
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_FORBIDDEN,
                'message' => 'Access Denied',
            ],
        ];
    }

    #[DataProvider('provideDeleteShopAddressException')]
    public function testDeleteShopAddressException(
        array $options,
        array $exception,
    ): void {
        $this->testException(Request::METHOD_DELETE, $this->iri, $options, $exception);
    }

    private static function getFakeDataShopAddress(): array
    {
        $faker = Factory::create('fr_FR');

        return [
            'name' => $faker->sentence(3, true),
            'firstname' => $faker->firstName(),
            'lastname' => $faker->lastName(),
            'company' => $faker->company(),
            'address' => $faker->streetAddress(),
            'zip' => $faker->postcode(),
            'city' => $faker->city(),
            'country' => $faker->country(),
            'phone' => $faker->phoneNumber(),
        ];
    }
}
