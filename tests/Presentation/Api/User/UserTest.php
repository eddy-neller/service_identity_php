<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Api\User;

use App\Domain\User\ValueObject\Access\RoleSet;
use App\Domain\User\ValueObject\Lifecycle\UserStatus;
use App\Infrastructure\Adapter\Token\TokenProvider;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity as User;
use App\Infrastructure\Symfony\DataFixtures\test\User\UserFixtures;
use App\Tests\Presentation\Api\BaseTest;
use Faker\Factory;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;

final class UserTest extends BaseTest
{
    protected const string URL_API_OPE = self::URL_API . 'users';

    protected const string URL_LOGIN = self::URL_API . 'auth/login';

    protected const string URL_REFRESH = self::URL_API . 'auth/token/refresh';

    protected const string URL_LOGOUT = self::URL_API . 'auth/token/invalidate';

    protected const string USER_DATA = 'user_member';

    public static function provideLoginSuccess(): Generator
    {
        yield 'Admin login' => [
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'email' => 'user_admin@en-develop.fr',
                    'password' => 'user_admin',
                ],
            ],
            [
                BaseTest::ASSERTION_TYPE['SERIALIZATION'] => [
                    'hasKey' => [
                        'accessToken',
                        'refreshToken',
                        'tokenType',
                        'expiresIn',
                    ],
                ],
                BaseTest::ASSERTION_TYPE['NOT_NULL'] => [
                    'accessToken',
                    'refreshToken',
                ],
            ],
        ];
    }

    #[DataProvider('provideLoginSuccess')]
    public function testLoginSuccess(
        array $options,
        array $asserts,
    ): void {
        $this->testSuccess(
            Request::METHOD_POST,
            self::URL_LOGIN,
            $options,
            Response::HTTP_OK,
            $asserts,
        );
    }

    public static function provideLoginException(): Generator
    {
        yield 'Bad credentials' => [
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'email' => 'user_admin@en-develop.fr',
                    'password' => 'wrong_password',
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'Invalid credentials.',
            ],
        ];
    }

    #[DataProvider('provideLoginException')]
    public function testLoginException(
        array $options,
        array $exception,
    ): void {
        $this->testException(
            Request::METHOD_POST,
            self::URL_LOGIN,
            $options,
            $exception,
        );
    }

    public static function provideLoginLock(): Generator
    {
        yield 'Lock after max attempts' => [
            'user_member_9@en-develop.fr',
            'wrong_password',
        ];
    }

    #[DataProvider('provideLoginLock')]
    public function testLoginLockAfterTooManyAttempts(string $email, string $password): void
    {
        $maxAttempts = (int) self::getContainer()->getParameter('app.security.max_login_attempts');

        for ($attempt = 1; $attempt <= $maxAttempts; ++$attempt) {
            $response = $this->request(
                Request::METHOD_POST,
                self::URL_LOGIN,
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'email' => $email,
                        'password' => $password,
                    ],
                ]
            );

            $this->assertNotNull($response);

            $status = $response->getStatusCode();
            if ($attempt < $maxAttempts) {
                $this->assertSame(Response::HTTP_UNAUTHORIZED, $status);

                continue;
            }

            $this->assertSame(Response::HTTP_LOCKED, $status);
        }
    }

    public function testRefreshTokenSuccess(): void
    {
        $login = $this->login(self::USER_DATA);

        self::assertArrayHasKey('refreshToken', $login);

        $this->testSuccess(
            Request::METHOD_POST,
            self::URL_REFRESH,
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'refreshToken' => $login['refreshToken'],
                ],
            ],
            Response::HTTP_OK,
            [
                BaseTest::ASSERTION_TYPE['SERIALIZATION'] => [
                    'hasKey' => [
                        'accessToken',
                        'refreshToken',
                        'tokenType',
                        'expiresIn',
                    ],
                ],
                BaseTest::ASSERTION_TYPE['NOT_NULL'] => [
                    'accessToken',
                    'refreshToken',
                ],
            ],
        );
    }

    public static function provideRefreshTokenException(): Generator
    {
        yield 'Empty request' => [
            [
                'json' => [],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'refreshToken: This value should not be blank.',
            ],
        ];
        yield 'Empty string token' => [
            [
                'json' => [
                    'refreshToken' => '',
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'refreshToken: This value should not be blank.',
            ],
        ];
        yield 'Unknown token' => [
            [
                'json' => [
                    'refreshToken' => 'unknown-refresh-token',
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'Invalid refresh token.',
            ],
        ];
    }

    #[DataProvider('provideRefreshTokenException')]
    public function testRefreshTokenException(
        array $options,
        array $exception,
    ): void {
        $this->testException(
            Request::METHOD_POST,
            self::URL_REFRESH,
            $options,
            $exception,
        );
    }

    public function testLogoutSuccess(): void
    {
        $login = $this->login(self::USER_DATA);

        self::assertArrayHasKey('refreshToken', $login);

        $this->testSuccess(
            Request::METHOD_POST,
            self::URL_LOGOUT,
            [
                'auth_bearer' => $login['accessToken'],
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'refreshToken' => $login['refreshToken'],
                ],
            ],
            Response::HTTP_NO_CONTENT,
        );
    }

    public static function provideLogoutException(): Generator
    {
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
        yield 'Missing refresh token' => [
            [
                'auth_bearer' => self::PLACEHOLDERS['TOKENS']['MEMBER'],
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'refreshToken: This value should not be blank.',
            ],
        ];
        yield 'Empty string refresh token' => [
            [
                'auth_bearer' => self::PLACEHOLDERS['TOKENS']['MEMBER'],
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'refreshToken' => '',
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'refreshToken: This value should not be blank.',
            ],
        ];
    }

    #[DataProvider('provideLogoutException')]
    public function testLogoutException(
        array $options,
        array $exception,
    ): void {
        $this->testException(
            Request::METHOD_POST,
            self::URL_LOGOUT,
            $options,
            $exception,
        );
    }

    public static function provideColUser(): Generator
    {
        $adminToken = self::PLACEHOLDERS['TOKENS']['ADMIN'];

        $assertions = [
            BaseTest::ASSERTION_TYPE['SERIALIZATION'] => [
                'hasKey' => [
                    'id',
                    'firstname',
                    'lastname',
                    'username',
                    'email',
                    'roles',
                    'status',
                    'avatarUrl',
                    'lastVisit',
                    'createdAt',
                    'updatedAt',
                ],
                'hasNotKey' => [
                    'nbLogin',
                    'password',
                    'avatarFile',
                ],
            ],
        ];

        yield 'Normal' => [
            [
                'auth_bearer' => $adminToken,
            ],
            $assertions,
        ];
        yield 'Pagin' => [
            [
                'auth_bearer' => $adminToken,
                'query' => self::generateQuery(
                    [
                        'page' => self::PAGIN_PAGE,
                        'ipp' => self::PAGIN_IPP,
                    ]
                ),
            ],
            $assertions,
        ];
        // TODO: Add filter exclude_id test
        yield 'Filter' => [
            [
                'auth_bearer' => $adminToken,
                'query' => self::generateQuery(
                    [
                        'filters' => [],
                    ]
                ),
            ],
            $assertions,
        ];
    }

    #[DataProvider('provideColUser')]
    public function testColUser(
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

    public static function provideColUserException(): Generator
    {
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
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_FORBIDDEN,
                'message' => 'Access Denied',
            ],
        ];
    }

    #[DataProvider('provideColUserException')]
    public function testColUserException(
        array $options,
        array $exception,
    ): void {
        $this->testException(
            Request::METHOD_GET,
            self::URL_API_OPE,
            $options,
            $exception
        );
    }

    public static function provideRegisterSuccess(): Generator
    {
        $fakeData = self::getFakeDataUser();

        $assertSerialization = [
            'hasKey' => [
                'id',
                'username',
                'email',
                'roles',
                'status',
                'lastVisit',
                'createdAt',
                'nbLogin',
                'updatedAt',
            ],
            'hasNotKey' => [
                'password',
                'plainPassword',
                'avatarFile',
            ],
        ];

        yield 'Full' => [
            [
                'json' => [
                    'email' => $fakeData['email'],
                    'username' => $fakeData['username'],
                    'password' => $fakeData['password'],
                    'preferences' => [
                        'lang' => 'EN',
                    ],
                ],
            ],
            [
                BaseTest::ASSERTION_TYPE['SERIALIZATION'] => $assertSerialization,
                BaseTest::ASSERTION_TYPE['EQUAL'] => [
                    'email' => $fakeData['email'],
                    'username' => $fakeData['username'],
                ],
            ],
        ];
    }

    #[DataProvider('provideRegisterSuccess')]
    public function testRegisterSuccess(
        array $options,
        array $asserts,
    ): void {
        $this->testSuccess(
            Request::METHOD_POST,
            self::URL_API_OPE . '/register',
            $options,
            Response::HTTP_CREATED,
            $asserts,
        );
    }

    public static function provideRegisterException(): Generator
    {
        $faker = Factory::create();

        yield 'Empty' => [
            [
                'json' => [],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'email: This value should not be blank.
username: This value should not be blank.
password: This value should not be blank.
preferences: This value should not be blank.',
            ],
        ];
        yield 'Email invalid' => [
            [
                'json' => [
                    'email' => $faker->sentence(),
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'email: This value is not a valid email address.',
            ],
        ];
        yield 'Preference invalid' => [
            [
                'json' => [
                    'preferences' => [],
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'preferences.lang: This value should not be blank.',
            ],
        ];
        yield 'Preference.lang invalid' => [
            [
                'json' => [
                    'preferences' => [
                        'lang' => 'F',
                    ],
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'preferences.lang: The language must be exactly 2 characters long.',
            ],
        ];
    }

    #[DataProvider('provideRegisterException')]
    public function testRegisterException(
        array $options,
        array $exception,
    ): void {
        $this->testException(Request::METHOD_POST, self::URL_API_OPE . '/register', $options, $exception);
    }

    public static function provideEmailActivationRequestSuccess(): Generator
    {
        $faker = Factory::create();

        yield 'Valid email' => [
            [
                'json' => [
                    'email' => $faker->email(),
                ],
            ],
        ];
    }

    #[DataProvider('provideEmailActivationRequestSuccess')]
    public function testEmailActivationRequestSuccess(
        array $options,
    ): void {
        $this->testSuccess(
            Request::METHOD_POST,
            self::URL_API_OPE . '/register/email-activation-request',
            $options,
            Response::HTTP_NO_CONTENT,
        );
    }

    public static function provideEmailActivationRequestException(): Generator
    {
        $faker = Factory::create();

        yield 'Empty request' => [
            [
                'json' => [],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'email: This value should not be blank.',
            ],
        ];
        yield 'Email invalid' => [
            [
                'json' => [
                    'email' => $faker->sentence(),
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'email: This value is not a valid email address.',
            ],
        ];
    }

    #[DataProvider('provideEmailActivationRequestException')]
    public function testEmailActivationRequestException(
        array $options,
        array $exception,
    ): void {
        $this->testException(
            Request::METHOD_POST,
            self::URL_API_OPE . '/register/email-activation-request',
            $options,
            $exception
        );
    }

    public static function provideEmailActivationValidationSuccess(): Generator
    {
        $encoded = base64_encode(UserFixtures::ACTIVATION_EMAIL . TokenProvider::TOKEN_SEPARATOR . UserFixtures::ACTIVATION_RAW_TOKEN);

        yield 'Valid token' => [
            [
                'json' => [
                    'token' => $encoded,
                ],
            ],
        ];
    }

    #[DataProvider('provideEmailActivationValidationSuccess')]
    public function testEmailActivationValidationSuccess(
        array $options,
    ): void {
        $this->testSuccess(
            Request::METHOD_POST,
            self::URL_API_OPE . '/register/validation',
            $options,
            Response::HTTP_NO_CONTENT,
        );
    }

    public static function provideEmailActivationValidationException(): Generator
    {
        yield 'Empty request' => [
            [
                'json' => [],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'token: This value should not be blank.',
            ],
        ];
        yield 'Missing token' => [
            [
                'json' => [
                    'username' => 'test',
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'token: This value should not be blank.',
            ],
        ];
        yield 'Empty string token' => [
            [
                'json' => [
                    'token' => '',
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'token: This value should not be blank.',
            ],
        ];
    }

    #[DataProvider('provideEmailActivationValidationException')]
    public function testEmailActivationValidationException(
        array $options,
        array $exception,
    ): void {
        $this->testException(
            Request::METHOD_POST,
            self::URL_API_OPE . '/register/validation',
            $options,
            $exception
        );
    }

    public static function providePasswordResetRequestSuccess(): Generator
    {
        $faker = Factory::create();

        yield 'Valid email' => [
            [
                'json' => [
                    'email' => $faker->email(),
                ],
            ],
        ];
    }

    #[DataProvider('providePasswordResetRequestSuccess')]
    public function testPasswordResetRequestSuccess(
        array $options,
    ): void {
        $this->testSuccess(
            Request::METHOD_POST,
            self::URL_API_OPE . '/reset-password/request',
            $options,
            Response::HTTP_NO_CONTENT,
        );
    }

    public static function providePasswordResetRequestException(): Generator
    {
        yield 'Empty request' => [
            [
                'json' => [],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'email: This value should not be blank.',
            ],
        ];
        yield 'Missing email' => [
            [
                'json' => [
                    'username' => 'test',
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'email: This value should not be blank.',
            ],
        ];
        yield 'Invalid email format' => [
            [
                'json' => [
                    'email' => 'invalid-email',
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'email: This value is not a valid email address.',
            ],
        ];
        yield 'Invalid email format with spaces' => [
            [
                'json' => [
                    'email' => ' invalid-email ',
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'email: This value is not a valid email address.',
            ],
        ];
        yield 'Empty string email' => [
            [
                'json' => [
                    'email' => '',
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'email: This value should not be blank.',
            ],
        ];
    }

    #[DataProvider('providePasswordResetRequestException')]
    public function testPasswordResetRequestException(
        array $options,
        array $exception,
    ): void {
        $this->testException(
            Request::METHOD_POST,
            self::URL_API_OPE . '/reset-password/request',
            $options,
            $exception
        );
    }

    public static function providePasswordResetCheckSuccess(): Generator
    {
        $encoded = base64_encode(UserFixtures::ACTIVATION_EMAIL . TokenProvider::TOKEN_SEPARATOR . UserFixtures::ACTIVATION_RAW_TOKEN);

        yield 'Valid token' => [
            [
                'json' => [
                    'token' => $encoded,
                ],
            ],
        ];
    }

    #[DataProvider('providePasswordResetCheckSuccess')]
    public function testPasswordResetCheckSuccess(
        array $options,
    ): void {
        $this->testSuccess(
            Request::METHOD_POST,
            self::URL_API_OPE . '/reset-password/check',
            $options,
            Response::HTTP_NO_CONTENT,
        );
    }

    public static function providePasswordResetCheckException(): Generator
    {
        yield 'Empty request' => [
            [
                'json' => [],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'token: This value should not be blank.',
            ],
        ];
        yield 'Missing token' => [
            [
                'json' => [
                    'email' => 'test@example.com',
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'token: This value should not be blank.',
            ],
        ];
        yield 'Empty string token' => [
            [
                'json' => [
                    'token' => '',
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'token: This value should not be blank.',
            ],
        ];
    }

    #[DataProvider('providePasswordResetCheckException')]
    public function testPasswordResetCheckException(
        array $options,
        array $exception,
    ): void {
        $this->testException(
            Request::METHOD_POST,
            self::URL_API_OPE . '/reset-password/check',
            $options,
            $exception
        );
    }

    public static function providePasswordResetConfirmSuccess(): Generator
    {
        $encoded = base64_encode(UserFixtures::ACTIVATION_EMAIL . TokenProvider::TOKEN_SEPARATOR . UserFixtures::ACTIVATION_RAW_TOKEN);

        yield 'Valid token and password' => [
            [
                'json' => [
                    'token' => $encoded,
                    'newPassword' => 'NewPassword123!',
                ],
            ],
        ];
    }

    #[DataProvider('providePasswordResetConfirmSuccess')]
    public function testPasswordResetConfirmSuccess(
        array $options,
    ): void {
        $this->testSuccess(
            Request::METHOD_POST,
            self::URL_API_OPE . '/reset-password/confirm',
            $options,
            Response::HTTP_NO_CONTENT,
        );
    }

    public static function providePasswordResetConfirmException(): Generator
    {
        yield 'Empty request' => [
            [
                'json' => [],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'token: This value should not be blank.
newPassword: This value should not be blank.',
            ],
        ];
        yield 'Missing token' => [
            [
                'json' => [
                    'password' => 'NewPassword123!',
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'token: This value should not be blank.',
            ],
        ];
        yield 'Missing new password' => [
            [
                'json' => [
                    'token' => 'valid-reset-token-123',
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'newPassword: This value should not be blank.',
            ],
        ];
        yield 'Empty string token' => [
            [
                'json' => [
                    'token' => '',
                    'password' => 'NewPassword123!',
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'token: This value should not be blank.',
            ],
        ];
        yield 'Empty string new password' => [
            [
                'json' => [
                    'token' => 'valid-reset-token-123',
                    'newPassword' => '',
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'newPassword: This value should not be blank.',
            ],
        ];
        yield 'Password too short' => [
            [
                'json' => [
                    'token' => 'valid-reset-token-123',
                    'newPassword' => 'Short1!',
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'newPassword: Invalid password.',
            ],
        ];
        yield 'Password too long' => [
            [
                'json' => [
                    'token' => 'valid-reset-token-123',
                    'newPassword' => 'VeryLongPasswordThatExceedsTheMaximumLengthAllowed123!',
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'newPassword: Invalid password.',
            ],
        ];
        yield 'Password without special character' => [
            [
                'json' => [
                    'token' => 'valid-reset-token-123',
                    'newPassword' => 'Password123',
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'newPassword: Invalid password.',
            ],
        ];
        yield 'Password without digit' => [
            [
                'json' => [
                    'token' => 'valid-reset-token-123',
                    'newPassword' => 'Password!@#',
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'newPassword: Invalid password.',
            ],
        ];
        yield 'Password without uppercase' => [
            [
                'json' => [
                    'token' => 'valid-reset-token-123',
                    'newPassword' => 'password123!',
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'newPassword: Invalid password.',
            ],
        ];
    }

    #[DataProvider('providePasswordResetConfirmException')]
    public function testPasswordResetConfirmException(
        array $options,
        array $exception,
    ): void {
        $this->testException(
            Request::METHOD_POST,
            self::URL_API_OPE . '/reset-password/confirm',
            $options,
            $exception
        );
    }

    public function testGetUser(): void
    {
        $adminToken = self::PLACEHOLDERS['TOKENS']['ADMIN'];

        $assertSerialization = [
            'hasKey' => [
                'id',
                'firstname',
                'lastname',
                'username',
                'email',
                'roles',
                'status',
                'avatarUrl',
                'lastVisit',
                'createdAt',
                'nbLogin',
                'updatedAt',
            ],
            'hasNotKey' => [
                'password',
                'plainPassword',
                'avatarFile',
            ],
        ];

        $this->testSuccess(
            Request::METHOD_GET,
            $this->userIri(self::USER_DATA),
            [
                'auth_bearer' => $adminToken,
            ],
            Response::HTTP_OK,
            [
                BaseTest::ASSERTION_TYPE['SERIALIZATION'] => $assertSerialization,
            ],
        );
    }

    public function testGetMe(): void
    {
        $assertSerialization = [
            'hasKey' => [
                'id',
                'firstname',
                'lastname',
                'username',
                'email',
                'roles',
                'status',
                'avatarUrl',
                'lastVisit',
                'createdAt',
                'nbLogin',
                'updatedAt',
            ],
            'hasNotKey' => [
                'password',
                'plainPassword',
                'avatarFile',
            ],
        ];

        $this->testSuccess(
            Request::METHOD_GET,
            self::URL_API_OPE . '/me',
            [
                'auth_bearer' => $this->getToken(self::USER_DATA),
            ],
            Response::HTTP_OK,
            [
                BaseTest::ASSERTION_TYPE['SERIALIZATION'] => $assertSerialization,
            ],
        );
    }

    public static function provideGetMeException(): Generator
    {
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
        yield 'Empty token' => [
            [
                'auth_bearer' => '',
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'HTTP 401 returned',
            ],
        ];
        yield 'Invalid token' => [
            [
                'auth_bearer' => 'invalid-token',
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'HTTP 401 returned',
            ],
        ];
        // Refuse pour sa signature, pas pour son `exp` : l'expiration est couverte par `api.jwt`.
        yield 'Forged signature' => [
            [
                'auth_bearer' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJpYXQiOjE2NzM5MjQwMDAsImV4cCI6MTY3MzkyNDAwMSwicm9sZXMiOlsiUk9MRV9VU0VSIl0sInVzZXJuYW1lIjoiZXhwaXJlZCJ9.expired-signature',
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'HTTP 401 returned',
            ],
        ];
        yield 'Malformed token' => [
            [
                'auth_bearer' => 'not-a-valid-jwt-token',
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'HTTP 401 returned',
            ],
        ];
        yield 'Token without Bearer prefix' => [
            [
                'auth_bearer' => 'valid-jwt-token-without-bearer-prefix',
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'HTTP 401 returned',
            ],
        ];
    }

    #[DataProvider('provideGetMeException')]
    public function testGetMeException(
        array $options,
        array $exception,
    ): void {
        $this->testException(
            Request::METHOD_GET,
            self::URL_API_OPE . '/me',
            $options,
            $exception
        );
    }

    public static function provideUpdatePasswordSuccess(): Generator
    {
        $ownerToken = self::PLACEHOLDERS['TOKENS']['MEMBER'];

        yield 'Update Password' => [
            [
                'auth_bearer' => $ownerToken,
                'headers' => [
                    'Content-Type' => 'application/merge-patch+json',
                ],
                'json' => [
                    'currentPassword' => self::USER_DATA,
                    'newPassword' => 'NewPassword123!',
                ],
            ],
        ];
    }

    #[DataProvider('provideUpdatePasswordSuccess')]
    public function testUpdatePasswordSuccess(
        array $options,
    ): void {
        $this->testSuccess(
            Request::METHOD_PATCH,
            self::URL_API_OPE . '/me/update-password',
            $options,
            Response::HTTP_NO_CONTENT,
        );
    }

    public static function provideUpdatePasswordException(): Generator
    {
        $faker = Factory::create();
        $ownerToken = self::PLACEHOLDERS['TOKENS']['MEMBER'];

        yield 'Bad Current Password' => [
            [
                'auth_bearer' => $ownerToken,
                'headers' => [
                    'Content-Type' => 'application/merge-patch+json',
                ],
                'json' => [
                    'currentPassword' => $faker->password(),
                    'newPassword' => 'NewPassword123!',
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'The current password is invalid.',
            ],
        ];
        yield 'Missing current password' => [
            [
                'auth_bearer' => $ownerToken,
                'headers' => [
                    'Content-Type' => 'application/merge-patch+json',
                ],
                'json' => [
                    'newPassword' => 'NewPassword123!',
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'currentPassword: This value should not be blank.',
            ],
        ];
        yield 'Missing new password' => [
            [
                'auth_bearer' => $ownerToken,
                'headers' => [
                    'Content-Type' => 'application/merge-patch+json',
                ],
                'json' => [
                    'currentPassword' => self::USER_DATA,
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'newPassword: This value should not be blank.',
            ],
        ];
        yield 'Same password as current' => [
            [
                'auth_bearer' => $ownerToken,
                'headers' => [
                    'Content-Type' => 'application/merge-patch+json',
                ],
                'json' => [
                    'currentPassword' => self::USER_DATA,
                    'newPassword' => self::USER_DATA,
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'newPassword: The new password must be different from the current password.',
            ],
        ];
        yield 'Weak password' => [
            [
                'auth_bearer' => $ownerToken,
                'headers' => [
                    'Content-Type' => 'application/merge-patch+json',
                ],
                'json' => [
                    'currentPassword' => self::USER_DATA,
                    'newPassword' => 'weak',
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'newPassword: Invalid new password.',
            ],
        ];
    }

    #[DataProvider('provideUpdatePasswordException')]
    public function testUpdatePasswordException(
        array $options,
        array $exception,
    ): void {
        $this->testException(
            Request::METHOD_PATCH,
            self::URL_API_OPE . '/me/update-password',
            $options,
            $exception
        );
    }

    /**
     * Images que les deux endpoints d'avatar doivent accepter. Partage par `/me/avatar`
     * et `/users/{id}/avatar` : ils passent par le meme DTO et le meme validateur.
     */
    public static function provideAcceptedAvatar(): Generator
    {
        yield 'JPEG 96x96, the minimum' => [self::PLACEHOLDERS['IMAGES']['AVATAR']];
        yield 'PNG' => [self::PLACEHOLDERS['IMAGES']['AVATAR_PNG']];
        yield 'WebP' => [self::PLACEHOLDERS['IMAGES']['AVATAR_WEBP']];
        yield 'JPEG 512x512, the maximum' => [self::PLACEHOLDERS['IMAGES']['AVATAR_MAX_DIMENSION']];
        yield 'JPEG of exactly AVATAR_MAX_SIZE bytes' => [self::PLACEHOLDERS['IMAGES']['AVATAR_MAX_SIZE']];
        yield 'PNG sent as avatar.jpg: the name is ignored' => [self::PLACEHOLDERS['IMAGES']['PNG_NAMED_JPG']];
    }

    /**
     * Images refusees, avec le message attendu. Les messages `avatarFile: …` viennent du
     * DTO (`Assert\File`), les autres du validateur de l'Infrastructure.
     */
    public static function provideRejectedAvatar(): Generator
    {
        $invalidMimeType = static fn (string $mimeType): string => sprintf(
            'avatarFile: The mime type of the file is invalid ("%s"). Allowed mime types are "image/jpeg", "image/png", "image/webp".',
            $mimeType,
        );
        $invalidDimensions = 'Avatar dimensions must be between 96 and 512 pixels.';

        yield 'GIF' => [self::PLACEHOLDERS['IMAGES']['GIF'], $invalidMimeType('image/gif')];
        yield 'SVG' => [self::PLACEHOLDERS['IMAGES']['SVG'], $invalidMimeType('image/svg+xml')];
        yield 'PDF' => [self::PLACEHOLDERS['IMAGES']['PDF'], $invalidMimeType('application/pdf')];
        yield 'Plain text sent as avatar.jpg' => [self::PLACEHOLDERS['IMAGES']['TEXT_NAMED_JPG'], $invalidMimeType('text/plain')];
        yield 'Empty file' => [self::PLACEHOLDERS['IMAGES']['EMPTY'], 'avatarFile: An empty file is not allowed.'];
        yield 'Truncated JPEG' => [self::PLACEHOLDERS['IMAGES']['TRUNCATED'], 'Avatar file is not a readable image.'];
        yield 'One byte over AVATAR_MAX_SIZE' => [
            self::PLACEHOLDERS['IMAGES']['OVER_MAX_SIZE'],
            'Avatar file exceeds the maximum allowed size (2097152 bytes).',
        ];
        yield 'Over the DTO upload limit' => [
            self::PLACEHOLDERS['IMAGES']['OVER_UPLOAD_LIMIT'],
            'avatarFile: The file is too large',
        ];
        yield 'Width below the minimum (95x96)' => [self::PLACEHOLDERS['IMAGES']['TOO_NARROW'], $invalidDimensions];
        yield 'Height below the minimum (96x95)' => [self::PLACEHOLDERS['IMAGES']['TOO_SHORT'], $invalidDimensions];
        yield 'Width above the maximum (513x96)' => [self::PLACEHOLDERS['IMAGES']['TOO_WIDE'], $invalidDimensions];
        yield 'Height above the maximum (96x513)' => [self::PLACEHOLDERS['IMAGES']['TOO_TALL'], $invalidDimensions];
        yield 'Both sides above the maximum (800x600)' => [self::PLACEHOLDERS['IMAGES']['PAYSAGE'], $invalidDimensions];
    }

    #[DataProvider('provideAcceptedAvatar')]
    public function testEditAvatarSuccess(string $image): void
    {
        $this->testSuccess(
            Request::METHOD_POST,
            self::URL_API_OPE . '/me/avatar',
            $this->avatarOptions($image, self::PLACEHOLDERS['TOKENS']['MEMBER']),
            Response::HTTP_CREATED,
            [
                BaseTest::ASSERTION_TYPE['NOT_NULL'] => ['avatarUrl'],
            ],
        );
    }

    #[DataProvider('provideRejectedAvatar')]
    public function testEditAvatarRejectsImage(string $image, string $message): void
    {
        $this->testException(
            Request::METHOD_POST,
            self::URL_API_OPE . '/me/avatar',
            $this->avatarOptions($image, self::PLACEHOLDERS['TOKENS']['MEMBER']),
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => $message,
            ],
        );
    }

    public static function provideEditAvatarException(): Generator
    {
        $ownerToken = self::PLACEHOLDERS['TOKENS']['MEMBER'];

        yield 'No role' => [
            [
                'extra' => [
                    'files' => [
                        'avatarFile' => self::PLACEHOLDERS['IMAGES']['AVATAR'],
                    ],
                ],
                'headers' => ['Content-Type' => 'multipart/form-data'],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'HTTP 401 returned',
            ],
        ];
        yield 'Missing file' => [
            [
                'headers' => ['Content-Type' => 'multipart/form-data'],
                'auth_bearer' => $ownerToken,
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'avatarFile: Please upload an avatar.',
            ],
        ];
        yield 'Wrong content type header' => [
            [
                'extra' => [
                    'files' => [
                        'avatarFile' => self::PLACEHOLDERS['IMAGES']['AVATAR'],
                    ],
                ],
                'headers' => ['Content-Type' => 'application/json'],
                'auth_bearer' => $ownerToken,
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNSUPPORTED_MEDIA_TYPE,
                'message' => 'The content-type "application/json" is not supported.',
            ],
        ];
    }

    #[DataProvider('provideEditAvatarException')]
    public function testEditAvatarException(
        array $options,
        array $exception,
    ): void {
        $this->testException(
            Request::METHOD_POST,
            self::URL_API_OPE . '/me/avatar',
            $options,
            $exception
        );
    }

    #[DataProvider('provideAcceptedAvatar')]
    public function testEditUserAvatarSuccess(string $image): void
    {
        $this->testSuccess(
            Request::METHOD_POST,
            $this->userIri(self::USER_DATA) . '/avatar',
            $this->avatarOptions($image, self::PLACEHOLDERS['TOKENS']['ADMIN']),
            Response::HTTP_CREATED,
            [
                BaseTest::ASSERTION_TYPE['NOT_NULL'] => ['avatarUrl'],
            ],
        );
    }

    #[DataProvider('provideRejectedAvatar')]
    public function testEditUserAvatarRejectsImage(string $image, string $message): void
    {
        $this->testException(
            Request::METHOD_POST,
            $this->userIri(self::USER_DATA) . '/avatar',
            $this->avatarOptions($image, self::PLACEHOLDERS['TOKENS']['ADMIN']),
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => $message,
            ],
        );
    }

    public static function provideEditUserAvatarException(): Generator
    {
        $adminToken = self::PLACEHOLDERS['TOKENS']['ADMIN'];
        $image = self::PLACEHOLDERS['IMAGES']['AVATAR'];

        yield 'No role' => [
            [
                'extra' => ['files' => ['avatarFile' => $image]],
                'headers' => ['Content-Type' => 'multipart/form-data'],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'HTTP 401 returned',
            ],
        ];
        yield 'Not admin' => [
            [
                'extra' => ['files' => ['avatarFile' => $image]],
                'headers' => ['Content-Type' => 'multipart/form-data'],
                'auth_bearer' => self::PLACEHOLDERS['TOKENS']['MEMBER'],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_FORBIDDEN,
                'message' => 'Access Denied',
            ],
        ];
        yield 'Missing file' => [
            [
                'headers' => ['Content-Type' => 'multipart/form-data'],
                'auth_bearer' => $adminToken,
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'avatarFile: Please upload an avatar.',
            ],
        ];
        yield 'Wrong content type header' => [
            [
                'extra' => ['files' => ['avatarFile' => $image]],
                'headers' => ['Content-Type' => 'application/json'],
                'auth_bearer' => $adminToken,
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNSUPPORTED_MEDIA_TYPE,
                'message' => 'The content-type "application/json" is not supported.',
            ],
        ];
    }

    #[DataProvider('provideEditUserAvatarException')]
    public function testEditUserAvatarException(
        array $options,
        array $exception,
    ): void {
        $this->testException(
            Request::METHOD_POST,
            $this->userIri(self::USER_DATA) . '/avatar',
            $options,
            $exception
        );
    }

    public function testEditUserAvatarOfUnknownUser(): void
    {
        $this->testException(
            Request::METHOD_POST,
            self::URL_API_OPE . '/5f0c8a4e-2b1d-4c3a-9e7f-1a2b3c4d5e6f/avatar',
            $this->avatarOptions(self::PLACEHOLDERS['IMAGES']['AVATAR'], self::PLACEHOLDERS['TOKENS']['ADMIN']),
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_NOT_FOUND,
                'message' => null,
            ],
        );
    }

    public static function provideDeleteUserSuccess(): Generator
    {
        $adminToken = self::PLACEHOLDERS['TOKENS']['ADMIN'];

        yield 'Full: Admin' => [
            [
                'auth_bearer' => $adminToken,
            ],
        ];
    }

    #[DataProvider('provideDeleteUserSuccess')]
    public function testDeleteUserSuccess(
        array $options,
    ): void {
        $this->testSuccess(
            Request::METHOD_DELETE,
            $this->userIri(self::USER_DATA),
            $options,
            Response::HTTP_NO_CONTENT,
        );
    }

    public static function provideDeleteUserException(): Generator
    {
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
    }

    #[DataProvider('provideDeleteUserException')]
    public function testDeleteUserException(
        array $options,
        array $exception,
    ): void {
        $this->testException(Request::METHOD_DELETE, $this->userIri(self::USER_DATA), $options, $exception);
    }

    public static function provideCreateUserByAdminSuccess(): Generator
    {
        $adminToken = self::PLACEHOLDERS['TOKENS']['ADMIN'];
        $fakeData = self::getFakeDataUser();

        $assertSerialization = [
            'hasKey' => [
                'id',
                'username',
                'email',
                'roles',
                'status',
                'lastVisit',
                'createdAt',
                'nbLogin',
                'updatedAt',
            ],
            'hasNotKey' => [
                'password',
                'plainPassword',
                'avatarFile',
            ],
        ];

        yield 'Full with all fields' => [
            [
                'auth_bearer' => $adminToken,
                'json' => [
                    'email' => $fakeData['email'],
                    'username' => $fakeData['username'],
                    'password' => $fakeData['password'],
                    'firstname' => 'John',
                    'lastname' => 'Doe',
                    'roles' => [RoleSet::ROLE_USER],
                    'status' => UserStatus::ACTIVE,
                ],
            ],
            [
                BaseTest::ASSERTION_TYPE['SERIALIZATION'] => $assertSerialization,
                BaseTest::ASSERTION_TYPE['EQUAL'] => [
                    'email' => $fakeData['email'],
                    'username' => $fakeData['username'],
                ],
            ],
        ];
        yield 'Minimal required fields' => [
            [
                'auth_bearer' => $adminToken,
                'json' => [
                    'email' => $fakeData['email'],
                    'username' => $fakeData['username'],
                    'password' => $fakeData['password'],
                    'roles' => [RoleSet::ROLE_USER],
                    'status' => UserStatus::INACTIVE,
                ],
            ],
            [
                BaseTest::ASSERTION_TYPE['SERIALIZATION'] => $assertSerialization,
                BaseTest::ASSERTION_TYPE['EQUAL'] => [
                    'email' => $fakeData['email'],
                    'username' => $fakeData['username'],
                ],
            ],
        ];
        yield 'With moderator role' => [
            [
                'auth_bearer' => $adminToken,
                'json' => [
                    'email' => $fakeData['email'],
                    'username' => $fakeData['username'],
                    'password' => $fakeData['password'],
                    'roles' => [RoleSet::ROLE_MODERATEUR],
                    'status' => UserStatus::ACTIVE,
                ],
            ],
            [
                BaseTest::ASSERTION_TYPE['SERIALIZATION'] => $assertSerialization,
            ],
        ];
        yield 'With admin role' => [
            [
                'auth_bearer' => $adminToken,
                'json' => [
                    'email' => $fakeData['email'],
                    'username' => $fakeData['username'],
                    'password' => $fakeData['password'],
                    'roles' => [RoleSet::ROLE_ADMIN],
                    'status' => UserStatus::ACTIVE,
                ],
            ],
            [
                BaseTest::ASSERTION_TYPE['SERIALIZATION'] => $assertSerialization,
            ],
        ];
        yield 'With multiple roles' => [
            [
                'auth_bearer' => $adminToken,
                'json' => [
                    'email' => $fakeData['email'],
                    'username' => $fakeData['username'],
                    'password' => $fakeData['password'],
                    'roles' => [RoleSet::ROLE_USER, RoleSet::ROLE_MODERATEUR],
                    'status' => UserStatus::ACTIVE,
                ],
            ],
            [
                BaseTest::ASSERTION_TYPE['SERIALIZATION'] => $assertSerialization,
            ],
        ];
        yield 'Blocked status' => [
            [
                'auth_bearer' => $adminToken,
                'json' => [
                    'email' => $fakeData['email'],
                    'username' => $fakeData['username'],
                    'password' => $fakeData['password'],
                    'roles' => [RoleSet::ROLE_USER],
                    'status' => UserStatus::BLOCKED,
                ],
            ],
            [
                BaseTest::ASSERTION_TYPE['SERIALIZATION'] => $assertSerialization,
            ],
        ];
    }

    #[DataProvider('provideCreateUserByAdminSuccess')]
    public function testCreateUserByAdminSuccess(
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

    public static function provideCreateUserByAdminException(): Generator
    {
        $faker = Factory::create();
        $adminToken = self::PLACEHOLDERS['TOKENS']['ADMIN'];

        yield 'Empty request' => [
            [
                'auth_bearer' => $adminToken,
                'json' => [],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'email: This value should not be blank.
username: This value should not be blank.
password: This value should not be blank.
roles: This value should not be blank.
status: This value should not be blank.',
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
                    'email' => $faker->email(),
                    'username' => $faker->userName(),
                    'password' => 'ValidPassword123!',
                    'roles' => [RoleSet::ROLE_USER],
                    'status' => UserStatus::ACTIVE,
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_FORBIDDEN,
                'message' => 'Access Denied',
            ],
        ];
        yield 'Email invalid' => [
            [
                'auth_bearer' => $adminToken,
                'json' => [
                    'email' => $faker->sentence(),
                    'username' => $faker->userName(),
                    'password' => 'ValidPassword123!',
                    'roles' => [RoleSet::ROLE_USER],
                    'status' => UserStatus::ACTIVE,
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'email: This value is not a valid email address.',
            ],
        ];
        yield 'Username too short' => [
            [
                'auth_bearer' => $adminToken,
                'json' => [
                    'email' => $faker->email(),
                    'username' => 'a',
                    'password' => 'ValidPassword123!',
                    'roles' => [RoleSet::ROLE_USER],
                    'status' => UserStatus::ACTIVE,
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'username: The username must be at least 2 characters long.',
            ],
        ];
        yield 'Username too long' => [
            [
                'auth_bearer' => $adminToken,
                'json' => [
                    'email' => $faker->email(),
                    'username' => str_repeat('a', 21),
                    'password' => 'ValidPassword123!',
                    'roles' => [RoleSet::ROLE_USER],
                    'status' => UserStatus::ACTIVE,
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'username: The username must be at most 20 characters long.',
            ],
        ];
        yield 'Password too short' => [
            [
                'auth_bearer' => $adminToken,
                'json' => [
                    'email' => $faker->email(),
                    'username' => $faker->userName(),
                    'password' => 'Short1!',
                    'roles' => [RoleSet::ROLE_USER],
                    'status' => UserStatus::ACTIVE,
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'password: Invalid password.',
            ],
        ];
        yield 'Password too long' => [
            [
                'auth_bearer' => $adminToken,
                'json' => [
                    'email' => $faker->email(),
                    'username' => $faker->userName(),
                    'password' => 'VeryLongPasswordThatExceedsTheMaximumLengthAllowed123!',
                    'roles' => [RoleSet::ROLE_USER],
                    'status' => UserStatus::ACTIVE,
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'password: Invalid password.',
            ],
        ];
        yield 'Password without special character' => [
            [
                'auth_bearer' => $adminToken,
                'json' => [
                    'email' => $faker->email(),
                    'username' => $faker->userName(),
                    'password' => 'Password123',
                    'roles' => [RoleSet::ROLE_USER],
                    'status' => UserStatus::ACTIVE,
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'password: Invalid password.',
            ],
        ];
        yield 'Password without digit' => [
            [
                'auth_bearer' => $adminToken,
                'json' => [
                    'email' => $faker->email(),
                    'username' => $faker->userName(),
                    'password' => 'Password!@#',
                    'roles' => [RoleSet::ROLE_USER],
                    'status' => UserStatus::ACTIVE,
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'password: Invalid password.',
            ],
        ];
        yield 'Password without uppercase' => [
            [
                'auth_bearer' => $adminToken,
                'json' => [
                    'email' => $faker->email(),
                    'username' => $faker->userName(),
                    'password' => 'password123!',
                    'roles' => [RoleSet::ROLE_USER],
                    'status' => UserStatus::ACTIVE,
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'password: Invalid password.',
            ],
        ];
        yield 'Firstname too short' => [
            [
                'auth_bearer' => $adminToken,
                'json' => [
                    'email' => $faker->email(),
                    'username' => $faker->userName(),
                    'password' => 'ValidPassword123!',
                    'firstname' => 'a',
                    'roles' => [RoleSet::ROLE_USER],
                    'status' => UserStatus::ACTIVE,
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'firstname: The firstname must be at least 2 characters long.',
            ],
        ];
        yield 'Firstname too long' => [
            [
                'auth_bearer' => $adminToken,
                'json' => [
                    'email' => $faker->email(),
                    'username' => $faker->userName(),
                    'password' => 'ValidPassword123!',
                    'firstname' => str_repeat('a', 51),
                    'roles' => [RoleSet::ROLE_USER],
                    'status' => UserStatus::ACTIVE,
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'firstname: The firstname must be at most 50 characters long.',
            ],
        ];
        yield 'Lastname too short' => [
            [
                'auth_bearer' => $adminToken,
                'json' => [
                    'email' => $faker->email(),
                    'username' => $faker->userName(),
                    'password' => 'ValidPassword123!',
                    'lastname' => 'a',
                    'roles' => [RoleSet::ROLE_USER],
                    'status' => UserStatus::ACTIVE,
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'lastname: The lastname must be at least 2 characters long.',
            ],
        ];
        yield 'Lastname too long' => [
            [
                'auth_bearer' => $adminToken,
                'json' => [
                    'email' => $faker->email(),
                    'username' => $faker->userName(),
                    'password' => 'ValidPassword123!',
                    'lastname' => str_repeat('a', 51),
                    'roles' => [RoleSet::ROLE_USER],
                    'status' => UserStatus::ACTIVE,
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'lastname: The lastname must be at most 50 characters long.',
            ],
        ];
        yield 'Invalid role' => [
            [
                'auth_bearer' => $adminToken,
                'json' => [
                    'email' => $faker->email(),
                    'username' => $faker->userName(),
                    'password' => 'ValidPassword123!',
                    'roles' => ['ROLE_INVALID'],
                    'status' => UserStatus::ACTIVE,
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'roles: One or more of the given values is invalid.',
            ],
        ];
        yield 'Empty roles array' => [
            [
                'auth_bearer' => $adminToken,
                'json' => [
                    'email' => $faker->email(),
                    'username' => $faker->userName(),
                    'password' => 'ValidPassword123!',
                    'roles' => [],
                    'status' => UserStatus::ACTIVE,
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'roles: This value should not be blank.',
            ],
        ];
        yield 'Invalid status' => [
            [
                'auth_bearer' => $adminToken,
                'json' => [
                    'email' => $faker->email(),
                    'username' => $faker->userName(),
                    'password' => 'ValidPassword123!',
                    'roles' => [RoleSet::ROLE_USER],
                    'status' => 999,
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'status: Invalid status.',
            ],
        ];
        yield 'Missing email' => [
            [
                'auth_bearer' => $adminToken,
                'json' => [
                    'username' => $faker->userName(),
                    'password' => 'ValidPassword123!',
                    'roles' => [RoleSet::ROLE_USER],
                    'status' => UserStatus::ACTIVE,
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'email: This value should not be blank.',
            ],
        ];
        yield 'Missing username' => [
            [
                'auth_bearer' => $adminToken,
                'json' => [
                    'email' => $faker->email(),
                    'password' => 'ValidPassword123!',
                    'roles' => [RoleSet::ROLE_USER],
                    'status' => UserStatus::ACTIVE,
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'username: This value should not be blank.',
            ],
        ];
        yield 'Missing password' => [
            [
                'auth_bearer' => $adminToken,
                'json' => [
                    'email' => $faker->email(),
                    'username' => $faker->userName(),
                    'roles' => [RoleSet::ROLE_USER],
                    'status' => UserStatus::ACTIVE,
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'password: This value should not be blank.',
            ],
        ];
        yield 'Missing roles' => [
            [
                'auth_bearer' => $adminToken,
                'json' => [
                    'email' => $faker->email(),
                    'username' => $faker->userName(),
                    'password' => 'ValidPassword123!',
                    'status' => UserStatus::ACTIVE,
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'roles: This value should not be blank.',
            ],
        ];
        yield 'Missing status' => [
            [
                'auth_bearer' => $adminToken,
                'json' => [
                    'email' => $faker->email(),
                    'username' => $faker->userName(),
                    'password' => 'ValidPassword123!',
                    'roles' => [RoleSet::ROLE_USER],
                ],
            ],
            [
                'class' => ClientExceptionInterface::class,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'status: This value should not be blank.',
            ],
        ];
    }

    #[DataProvider('provideCreateUserByAdminException')]
    public function testCreateUserByAdminException(
        array $options,
        array $exception,
    ): void {
        $this->testException(
            Request::METHOD_POST,
            self::URL_API_OPE,
            $options,
            $exception
        );
    }

    private static function getFakeDataUser(): array
    {
        $faker = Factory::create();

        return [
            'email' => $faker->email(),
            'username' => $faker->userName(),
            'password' => 'User_max88',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function avatarOptions(string $image, string $token): array
    {
        return [
            'extra' => ['files' => ['avatarFile' => $image]],
            'headers' => ['Content-Type' => 'multipart/form-data'],
            'auth_bearer' => $token,
        ];
    }

    private function userIri(string $username): string
    {
        $user = $this->getInstance(User::class, ['username' => $username]);
        self::assertInstanceOf(User::class, $user);

        return self::URL_API_OPE . '/' . $user->getId()->toString();
    }
}
