<?php

declare(strict_types=1);

namespace App\Presentation\Tests\Api\Shop;

use App\Presentation\Tests\Api\BaseTest;
use Faker\Factory;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;

final class CategoryTest extends BaseTest
{
    protected const string URL_API_OPE = self::URL_API . 'shop/categories';

    public const array CRITERIA_IRI = ['title' => 'Shop category level 1 title 1'];

    private const string UNKNOWN_ID = '550e8400-e29b-41d4-a716-446655440099';

    private const string INVALID_ID = 'invalid-uuid';

    protected ?string $iri;

    protected function setUp(): void
    {
        parent::setUp();

        $this->iri = $this->findIriByHttp(self::URL_API_OPE, self::CRITERIA_IRI, $this->userAdmin);
    }

    public static function provideColShopCategory(): Generator
    {
        $assertions = [
            BaseTest::ASSERTION_TYPE['SERIALIZATION'] => [
                'hasKey' => [
                    'id',
                    'title',
                    'nbProduct',
                    'slug',
                    'level',
                    'createdAt',
                    'updatedAt',
                ],
                'hasNotKey' => [
                    'description',
                    'parent',
                    'children',
                ],
            ],
        ];

        yield 'Normal' => [
            [],
            $assertions,
        ];

        yield 'Pagin' => [
            [
                'query' => self::generateQuery(
                    [
                        'page' => self::PAGIN_PAGE,
                        'ipp' => self::PAGIN_IPP,
                    ]
                ),
            ],
            $assertions,
        ];

        yield 'Order' => [
            [
                'query' => self::generateQuery(
                    [
                        'filters' => [
                            [
                                'filter' => 'order',
                                'field' => 'title',
                                'sort' => 'ASC',
                            ],
                        ],
                    ]
                ),
            ],
            $assertions,
        ];

        yield 'Filter' => [
            [
                'query' => self::generateQuery(
                    [
                        'filters' => [
                            [
                                'filter' => 'search',
                                'field' => 'level',
                                'value' => 1,
                            ],
                        ],
                    ]
                ),
            ],
            $assertions,
        ];
    }

    #[DataProvider('provideColShopCategory')]
    public function testColShopCategory(
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

    public static function provideCreateShopCategorySuccess(): Generator
    {
        $fakeData = self::getFakeDataShopCategory();
        $adminToken = self::PLACEHOLDERS['TOKENS']['ADMIN'];

        $assertSerialization = [
            'hasKey' => [
                'id',
                'title',
                'description',
                'nbProduct',
                'slug',
                'level',
                'createdAt',
                'updatedAt',
            ],
            'hasNotKey' => [
                'parent', // pas de parent donc level 0
                'children',
            ],
        ];

        yield 'Full' => [
            [
                'auth_bearer' => $adminToken,
                'json' => [
                    'title' => $fakeData['title'],
                    'description' => $fakeData['description'],
                ],
            ],
            [
                BaseTest::ASSERTION_TYPE['SERIALIZATION'] => $assertSerialization,
                BaseTest::ASSERTION_TYPE['EQUAL'] => [
                    'title' => $fakeData['title'],
                    'description' => $fakeData['description'],
                ],
            ],
        ];
    }

    #[DataProvider('provideCreateShopCategorySuccess')]
    public function testCreateShopCategorySuccess(
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

    public static function provideCreateCategoryException(): Generator
    {
        $adminToken = self::PLACEHOLDERS['TOKENS']['ADMIN'];

        yield 'Empty' => [
            [
                'auth_bearer' => $adminToken,
                'json' => [],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'title: This value should not be blank.',
            ],
        ];
        yield 'No role' => [
            [
                'json' => [],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'HTTP 401 returned',
            ],
        ];
        yield 'Not admin' => [
            [
                'auth_bearer' => self::PLACEHOLDERS['TOKENS']['MEMBER'],
                'json' => [
                    'title' => 'Shop category title',
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_FORBIDDEN,
                'message' => 'Access Denied',
            ],
        ];
        yield 'Same title' => [
            [
                'auth_bearer' => $adminToken,
                'json' => [
                    'title' => 'Shop category level 1 title 1',
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'title: A shop category already has this title.',
            ],
        ];
        yield 'Title too short' => [
            [
                'auth_bearer' => $adminToken,
                'json' => [
                    'title' => 'a',
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'title: The title must be at least 2 characters long.',
            ],
        ];
        yield 'Title too long' => [
            [
                'auth_bearer' => $adminToken,
                'json' => [
                    'title' => str_repeat('a', 101),
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'title: The title must be at most 100 characters long.',
            ],
        ];
        yield 'Description too short' => [
            [
                'auth_bearer' => $adminToken,
                'json' => [
                    'title' => 'Valid category title',
                    'description' => 'a',
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'description: The description must be at least 2 characters long.',
            ],
        ];
        yield 'Description too long' => [
            [
                'auth_bearer' => $adminToken,
                'json' => [
                    'title' => 'Valid category title',
                    'description' => str_repeat('a', 1001),
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'description: The description must be at most 1000 characters long.',
            ],
        ];
    }

    #[DataProvider('provideCreateCategoryException')]
    public function testCreateCategoryException(
        array $options,
        array $exception,
    ): void {
        $this->testException(Request::METHOD_POST, self::URL_API_OPE, $options, $exception);
    }

    public function testGetShopCategory(): void
    {
        $adminToken = self::PLACEHOLDERS['TOKENS']['ADMIN'];
        $assertSerialization = [
            'hasKey' => [
                'id',
                'title',
                'description',
                'nbProduct',
                'slug',
                'level',
                'parent',
                'children',
                'createdAt',
                'updatedAt',
            ],
        ];

        $this->testSuccess(
            Request::METHOD_GET,
            $this->iri,
            [
                'auth_bearer' => $adminToken,
            ],
            Response::HTTP_OK,
            [
                BaseTest::ASSERTION_TYPE['SERIALIZATION'] => $assertSerialization,
            ],
        );
    }

    public static function provideUpdateShopCategorySuccess(): Generator
    {
        $fakeData = self::getFakeDataShopCategory();
        $adminToken = self::PLACEHOLDERS['TOKENS']['ADMIN'];

        $assertSerialization = [
            'hasKey' => [
                'id',
                'title',
                'description',
                'nbProduct',
                'slug',
                'level',
                'parent',
                'children',
                'createdAt',
                'updatedAt',
            ],
        ];

        yield 'Full' => [
            [
                'auth_bearer' => $adminToken,
                'headers' => [
                    'Content-Type' => 'application/merge-patch+json',
                ],
                'json' => [
                    'title' => $fakeData['title'],
                    'description' => $fakeData['description'],
                ],
            ],
            [
                BaseTest::ASSERTION_TYPE['SERIALIZATION'] => $assertSerialization,
                BaseTest::ASSERTION_TYPE['EQUAL'] => [
                    'title' => $fakeData['title'],
                    'description' => $fakeData['description'],
                ],
            ],
        ];
    }

    #[DataProvider('provideUpdateShopCategorySuccess')]
    public function testUpdateShopCategorySuccess(
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

    public static function provideUpdateCategoryException(): Generator
    {
        yield 'No role' => [
            [
                'headers' => [
                    'Content-Type' => 'application/merge-patch+json',
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'HTTP 401 returned',
            ],
        ];
        yield 'Not admin' => [
            [
                'auth_bearer' => self::PLACEHOLDERS['TOKENS']['MEMBER'],
                'headers' => [
                    'Content-Type' => 'application/merge-patch+json',
                ],
                'json' => [
                    'title' => 'Updated title',
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_FORBIDDEN,
                'message' => 'Access Denied',
            ],
        ];
        yield 'Title too short' => [
            [
                'auth_bearer' => self::PLACEHOLDERS['TOKENS']['ADMIN'],
                'headers' => [
                    'Content-Type' => 'application/merge-patch+json',
                ],
                'json' => [
                    'title' => 'a',
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'title: The title must be at least 2 characters long.',
            ],
        ];
        yield 'Description too long' => [
            [
                'auth_bearer' => self::PLACEHOLDERS['TOKENS']['ADMIN'],
                'headers' => [
                    'Content-Type' => 'application/merge-patch+json',
                ],
                'json' => [
                    'description' => str_repeat('a', 1001),
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'description: The description must be at most 1000 characters long.',
            ],
        ];
    }

    #[DataProvider('provideUpdateCategoryException')]
    public function testUpdateCategoryException(
        array $options,
        array $exception,
    ): void {
        $this->testException(Request::METHOD_PATCH, $this->iri, $options, $exception);
    }

    public static function provideDeleteShopCategorySuccess(): Generator
    {
        $adminToken = self::PLACEHOLDERS['TOKENS']['ADMIN'];

        yield 'Full: Admin' => [
            [
                'auth_bearer' => $adminToken,
            ],
        ];
    }

    #[DataProvider('provideDeleteShopCategorySuccess')]
    public function testDeleteShopCategorySuccess(
        array $options,
    ): void {
        $this->testSuccess(
            Request::METHOD_DELETE,
            $this->iri,
            $options,
            Response::HTTP_NO_CONTENT,
        );
    }

    public static function provideDeleteShopCategoryException(): Generator
    {
        yield 'No role' => [
            [],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'HTTP 401 returned',
            ],
        ];
        yield 'Not admin' => [
            [
                'auth_bearer' => self::PLACEHOLDERS['TOKENS']['MEMBER'],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_FORBIDDEN,
                'message' => 'Access Denied',
            ],
        ];
    }

    #[DataProvider('provideDeleteShopCategoryException')]
    public function testDeleteShopCategoryException(
        array $options,
        array $exception,
    ): void {
        $this->testException(Request::METHOD_DELETE, $this->iri, $options, $exception);
    }

    public static function provideCategoryNotFoundException(): Generator
    {
        $adminToken = self::PLACEHOLDERS['TOKENS']['ADMIN'];

        yield 'Get not found' => [
            Request::METHOD_GET,
            self::URL_API_OPE . '/' . self::UNKNOWN_ID,
            [],
        ];
        yield 'Patch not found' => [
            Request::METHOD_PATCH,
            self::URL_API_OPE . '/' . self::UNKNOWN_ID,
            [
                'auth_bearer' => $adminToken,
                'headers' => [
                    'Content-Type' => 'application/merge-patch+json',
                ],
                'json' => [
                    'title' => 'Updated title',
                ],
            ],
        ];
        yield 'Delete not found' => [
            Request::METHOD_DELETE,
            self::URL_API_OPE . '/' . self::UNKNOWN_ID,
            [
                'auth_bearer' => $adminToken,
            ],
        ];
        yield 'Get invalid id' => [
            Request::METHOD_GET,
            self::URL_API_OPE . '/' . self::INVALID_ID,
            [],
        ];
        yield 'Patch invalid id' => [
            Request::METHOD_PATCH,
            self::URL_API_OPE . '/' . self::INVALID_ID,
            [
                'auth_bearer' => $adminToken,
                'headers' => [
                    'Content-Type' => 'application/merge-patch+json',
                ],
                'json' => [
                    'title' => 'Updated title',
                ],
            ],
        ];
        yield 'Delete invalid id' => [
            Request::METHOD_DELETE,
            self::URL_API_OPE . '/' . self::INVALID_ID,
            [
                'auth_bearer' => $adminToken,
            ],
        ];
    }

    #[DataProvider('provideCategoryNotFoundException')]
    public function testCategoryNotFoundException(
        string $method,
        string $uri,
        array $options,
    ): void {
        $this->testException($method, $uri, $options, [
            'class' => ClientExceptionInterface::class,
            'code' => Response::HTTP_NOT_FOUND,
        ]);
    }

    private static function getFakeDataShopCategory(): array
    {
        $faker = Factory::create();

        return [
            'title' => $faker->sentence(5),
            'description' => $faker->text(),
        ];
    }
}
