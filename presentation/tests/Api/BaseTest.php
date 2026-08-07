<?php

declare(strict_types=1);

namespace App\Presentation\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Presentation\Tests\Api\User\UserTest;
use Doctrine\ORM\EntityManagerInterface;
use Faker\Factory;
use Faker\Generator;
use JsonException;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @SuppressWarnings("PMD")
 */
abstract class BaseTest extends ApiTestCase
{
    protected const string URL_API = '/api/';

    public const array ASSERTION_TYPE = [
        'SERIALIZATION' => 'serialization',
        'EQUAL' => 'equals',
        'NULL' => 'null',
        'NOT_NULL' => 'notNull',
        'DATE' => 'date',
        'TRANSLATION' => 'translation',
        'IRI' => 'iri',
        'EMPTY' => 'empty',
        'PAGINATION' => 'pagin',
        'FILTER' => 'filter',
    ];

    protected const int PAGIN_IPP = 2;

    protected const int PAGIN_PAGE_ONE = 1;

    protected const int PAGIN_PAGE = 3;

    protected const array MEDIA_TYPE = [
        'IMAGE' => 'IMAGE',
    ];

    protected const array PLACEHOLDERS = [
        'TOKENS' => [
            'ADMIN' => 'ADMIN_TOKEN_PLACEHOLDER',
            'MEMBER' => 'MEMBER_TOKEN_PLACEHOLDER',
            'MEMBER_1' => 'MEMBER_1_TOKEN_PLACEHOLDER',
        ],
        'IMAGES' => [
            'PAYSAGE' => 'PAYSAGE_IMAGE_PLACEHOLDER',
            'AVATAR' => 'AVATAR_IMAGE_PLACEHOLDER',
            'LARGE' => 'LARGE_IMAGE_PLACEHOLDER',
            'PDF' => 'PDF_IMAGE_PLACEHOLDER',
            'WIDE' => 'WIDE_IMAGE_PLACEHOLDER',
            'TALL' => 'TALL_IMAGE_PLACEHOLDER',
        ],
    ];

    private const array TOKEN_PLACEHOLDER_MAPPING = [
        'ADMIN_TOKEN_PLACEHOLDER' => 'user_admin',
        'MEMBER_TOKEN_PLACEHOLDER' => 'user_member',
        'MEMBER_1_TOKEN_PLACEHOLDER' => 'user_member_1',
    ];

    private const array IMAGE_PLACEHOLDER_MAPPING = [
        'PAYSAGE_IMAGE_PLACEHOLDER' => 'paysage.jpg',
        'AVATAR_IMAGE_PLACEHOLDER' => 'venom.jpg',
        'LARGE_IMAGE_PLACEHOLDER' => 'large_image.jpg',
        'PDF_IMAGE_PLACEHOLDER' => 'document.pdf',
        'WIDE_IMAGE_PLACEHOLDER' => 'wide_image.jpg',
        'TALL_IMAGE_PLACEHOLDER' => 'tall_image.jpg',
    ];

    protected Client $client;

    protected Generator $faker;

    protected EntityManagerInterface $em;

    protected string $userAdmin = 'user_admin';

    protected string $userModer = 'user_moder';

    protected string $userMember = 'user_member';

    protected array $listOtherAdmin = [
        'user_admin_1',
        'user_admin_2',
        'user_admin_3',
    ];

    protected array $listOtherModer = [
        'user_moder_4',
        'user_moder_5',
        'user_moder_6',
    ];

    protected array $listOtherMember = [
        'user_member_7',
        'user_member_8',
        'user_member_9',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Factory::create();
        $this->client = self::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    protected function getApiClient(): HttpClientInterface
    {
        return $this->client ?? self::createClient();
    }

    protected function getFaker(): Generator
    {
        return $this->faker ?? Factory::create();
    }

    protected function getManager(): EntityManagerInterface
    {
        return $this->em ?? static::getContainer()->get('doctrine')->getManager();
    }

    protected function request(string $method, string $url, array $options = []): ?ResponseInterface
    {
        try {
            if (!isset($options['headers'])) {
                $options['headers'] = [];
            }

            if (!isset($options['headers']['Accept'])) {
                $options['headers']['Accept'] = 'application/json';
            }

            return $this->getApiClient()->request($method, $url, $options);
        } catch (TransportExceptionInterface) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function login(string $username): array
    {
        try {
            $options = [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'email' => $username . '@en-develop.fr',
                    'password' => $username,
                ],
            ];

            $response = $this->getApiClient()->request(Request::METHOD_POST, UserTest::URL_LOGIN, $options);

            $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($data)) {
                throw new RuntimeException('Invalid login response');
            }

            return $data;
        } catch (
            ClientExceptionInterface|RedirectionExceptionInterface|
            ServerExceptionInterface|TransportExceptionInterface|JsonException $e
        ) {
            throw new RuntimeException('login: ' . $e->getMessage());
        }
    }

    protected function getToken(string $username): string
    {
        $data = $this->login($username);

        if (!isset($data['accessToken'])) {
            throw new RuntimeException('getToken: Token not found');
        }

        return $data['accessToken'];
    }

    protected function testSuccess(
        string $method,
        string $uri,
        array $options,
        int $code,
        array $asserts = [],
        bool $noTreatment = false,
    ): ?array {
        $options = $this->replacePlaceholders($options);

        $res = $this->request(
            $method,
            $uri,
            $options
        );

        try {
            $statusCode = $res->getStatusCode();
            $result = [];

            if (!in_array($statusCode, [204, 205], true)) {
                $content = $res->getContent();
                $result = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            }
        } catch (
            ClientExceptionInterface|RedirectionExceptionInterface|
            ServerExceptionInterface|TransportExceptionInterface|JsonException $e
        ) {
            throw new RuntimeException('testSuccess: invalid response: ' . $e->getMessage());
        }

        self::assertResponseIsSuccessful();
        self::assertResponseStatusCodeSame($code);

        if (!$noTreatment) {
            if (Request::METHOD_DELETE !== $method) {
                $isCollection = $this->isCollectionResponse($result);
                $res = $this->getTestResult($result, $isCollection);
                $this->makeAssertion($res, $asserts);

                return $res;
            }

            return null;
        }

        return $this->getTestResult($result);
    }

    protected function testException(
        string $method,
        string $uri,
        array $options,
        array $exception,
    ): void {
        $this->expectException($exception['class']);
        $this->expectExceptionCode($exception['code']);

        if (null !== $exception['message']) {
            $this->expectExceptionMessage($exception['message']);
        }

        $options = $this->replacePlaceholders($options);

        $response = $this->request(
            $method,
            $uri,
            $options
        );

        json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    protected function makeAssertion(
        array $res,
        array $asserts,
    ): void {
        foreach ($asserts as $type => $assert) {
            switch ($type) {
                case self::ASSERTION_TYPE['SERIALIZATION']:
                    foreach ($assert as $typeSerialization => $value) {
                        foreach ($value as $val) {
                            $this->analyseSerializeAssertion($val, $res, $typeSerialization);
                        }
                    }

                    break;
                case self::ASSERTION_TYPE['EQUAL']:
                    foreach ($assert as $key => $value) {
                        $this->assertEquals($res[$key], $value);
                    }

                    break;
                case self::ASSERTION_TYPE['NULL']:
                    foreach ($assert as $value) {
                        $this->assertNull($res[$value]);
                    }

                    break;
                case self::ASSERTION_TYPE['NOT_NULL']:
                    foreach ($assert as $value) {
                        $this->assertNotNull($res[$value]);
                    }

                    break;
                case self::ASSERTION_TYPE['DATE']:
                case self::ASSERTION_TYPE['TRANSLATION']:
                    foreach ($assert as $key => $value) {
                        $this->assertStringContainsString($value, (string) $res[$key]);
                    }

                    break;
                case self::ASSERTION_TYPE['EMPTY']:
                    $this->assertEmpty($res);
                    break;
                case self::ASSERTION_TYPE['PAGINATION']:
                case self::ASSERTION_TYPE['FILTER']:
                    break;
            }
        }
    }

    protected function switchKeySerialization(array $array, array $unwantedKeys): array
    {
        $hasKey = $array['hasKey'] ?? [];
        $hasNotKey = $array['hasNotKey'] ?? [];

        foreach ($unwantedKeys as $unwantedKey) {
            $keyIndex = array_search($unwantedKey, $hasKey, true);
            if (false !== $keyIndex) {
                unset($hasKey[$keyIndex]);
                $hasNotKey[] = $unwantedKey;
            }
        }

        $array['hasKey'] = array_values($hasKey);
        $array['hasNotKey'] = array_values($hasNotKey);

        return $array;
    }

    protected function calculateExpectedOrders(array $itemsBefore, object $itemToDelete): array
    {
        $expectedOrders = [];

        foreach ($itemsBefore as $item) {
            $itemId = $item->getId()->toString();
            $itemToDeleteId = $itemToDelete->getId()->toString();

            if ($itemId !== $itemToDeleteId) {
                $expectedOrders[$itemId] = $item->getOrdre() > $itemToDelete->getOrdre()
                    ? $item->getOrdre() - 1
                    : $item->getOrdre();
            }
        }

        return $expectedOrders;
    }

    protected function getCollection(mixed $class, array $criteria, ?int $limit = null): array
    {
        $qb = $this->getManager()->getRepository($class)->createQueryBuilder('o'); // @phpstan-ignore-line

        foreach ($criteria as $field => $value) {
            if (str_contains($field, '.')) {
                [$relation, $column] = explode('.', $field);
                $qb->innerJoin('o.' . $relation, 'r');
                $qb->andWhere(sprintf('r.%s = :criteria', $column));
            } else {
                $qb->andWhere(sprintf('o.%s = :criteria', $field));
            }

            $qb->setParameter('criteria', $value);
        }

        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    protected function getInstance(mixed $class, array $criteria): mixed
    {
        $this->getManager()->clear();

        return $this->getManager()->getRepository($class)->findOneBy($criteria); // @phpstan-ignore-line
    }

    protected function getImage(string $filename, string $suffix): UploadedFile
    {
        return $this->getPhysicalTempFile($filename, $suffix);
    }

    protected function getIdFromIri(string $iri): string
    {
        return array_reverse(explode('/', $iri))[0];
    }

    protected static function generateQuery(
        array $options = [],
    ): array {
        $query = [];

        // page
        if (array_key_exists('page', $options) && $options['page']) {
            $query['page'] = $options['page'];
        }

        // itemsPerPage
        if (array_key_exists('ipp', $options) && $options['ipp']) {
            $query['itemsPerPage'] = $options['ipp'];
        }

        // filters
        if (array_key_exists('filters', $options) && !empty($options['filters'])) {
            foreach ($options['filters'] as $value) {
                switch ($value['filter']) {
                    case 'exists':
                        $query['exists[' . $value['field'] . ']'] = $value['value'];
                        break;
                    case 'order':
                        $query['order[' . $value['field'] . ']'] = $value['sort'];
                        break;
                    case 'date':
                    case 'search':
                    case 'boolean':
                        $query[$value['field']] = $value['value'];
                        break;
                    case 'property':
                        foreach ($value['value'] as $v) {
                            $query['properties'][] = $v;
                        }

                        break;
                }
            }
        }

        return $query;
    }

    private function isCollectionResponse(array $res): bool
    {
        // Nouveau format paginé
        if (!array_key_exists('id', $res) && isset($res['items']) && is_array($res['items'])) {
            return array_is_list($res['items']) && isset($res['items'][0]['id']);
        }

        // Ancien format (liste brute)
        return array_is_list($res) && isset($res[0]['id']);
    }

    private function analyseSerializeAssertion(mixed $data, mixed $res, string $type): void
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    /* Assertion sur un subressource */
                    if ('hasKey' === $type) {
                        $this->serializeAssertion($key, $res, $type);
                    }

                    foreach ($value as $y) {
                        $this->analyseSerializeAssertion($y, $res[$key], $type);
                    }
                }
            }

            return;
        }

        $this->serializeAssertion($data, $res, $type);
    }

    private function serializeAssertion(mixed $data, mixed $res, string $type): void
    {
        switch ($type) {
            case 'hasKey':
                $this->assertArrayHasKey($data, $res);
                break;
            case 'hasNotKey':
                $this->assertArrayNotHasKey($data, $res);
                break;
        }
    }

    private function getTestResult(array $res, bool $onlyFirst = false): ?array
    {
        // Collection paginée (nouveau format avec 'items')
        if ($onlyFirst && isset($res['items']) && is_array($res['items'])) {
            $items = $res['items'];

            return $items[0] ?? null;
        }

        // Détection d'une collection simple (ancien format)
        if ($onlyFirst && [] !== $res && array_is_list($res)) {
            return $res[0];
        }

        // Sinon, renvoie tel quel (item unique ou vide)
        return $res;
    }

    private function replacePlaceholders(array $options): array
    {
        $options = $this->replaceTokenPlaceholders($options);

        return $this->replaceFilePlaceholders($options);
    }

    private function replaceTokenPlaceholders(array $options): array
    {
        if (!isset($options['auth_bearer'])) {
            return $options;
        }

        $tokenPlaceholder = $options['auth_bearer'];

        if (isset(self::TOKEN_PLACEHOLDER_MAPPING[$tokenPlaceholder])) {
            $username = self::TOKEN_PLACEHOLDER_MAPPING[$tokenPlaceholder];
            $options['auth_bearer'] = $this->getToken($username);
        }

        return $options;
    }

    private function replaceFilePlaceholders(array $options): array
    {
        if (isset($options['extra']['files']['imageFile']) && is_string($options['extra']['files']['imageFile'])) {
            $options['extra']['files']['imageFile'] = $this->replaceImagePlaceholder($options['extra']['files']['imageFile']);
        }

        if (isset($options['extra']['files']['avatarFile']) && is_string($options['extra']['files']['avatarFile'])) {
            $options['extra']['files']['avatarFile'] = $this->replaceImagePlaceholder($options['extra']['files']['avatarFile']);
        }

        return $options;
    }

    private function replaceImagePlaceholder(string $placeholder): UploadedFile|string
    {
        if (!isset(self::IMAGE_PLACEHOLDER_MAPPING[$placeholder])) {
            return $placeholder;
        }

        $filename = self::IMAGE_PLACEHOLDER_MAPPING[$placeholder];

        return $this->getImage($filename, __METHOD__);
    }

    private function getPhysicalTempFile(string $filename, string $suffix): UploadedFile
    {
        $dir = 'images';

        $cleanSuffix = explode('::', $suffix)[1];

        $tmpFilePath = sys_get_temp_dir() . '/' . $cleanSuffix . '.jpg';

        copy(
            static::getContainer()->getParameter('kernel.project_dir') . '/assets/tests/' . $dir . '/' . $filename,
            $tmpFilePath
        );

        return new UploadedFile($tmpFilePath, $filename);
    }
}
