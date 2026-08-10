<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Integration\CQRS;

use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\Shared\Messenger\Message\SendEmailMessage;
use App\Infrastructure\Messenger\CQRS\MessengerCommandBus;
use App\Infrastructure\Messenger\CQRS\MessengerQueryBus;
use App\Infrastructure\Messenger\Event\Handler\LogDomainEventHandler;
use App\Infrastructure\Messenger\Event\Handler\User\DisableCustomerHandler;
use App\Infrastructure\Messenger\Event\Handler\User\ProvisionCustomerHandler;
use App\Infrastructure\Messenger\Event\Handler\User\RevokeSessionsHandler;
use App\Infrastructure\Messenger\Event\Handler\User\SendActivationEmailHandler;
use App\Infrastructure\Messenger\Event\Handler\User\SendResetPasswordEmailHandler;
use App\Infrastructure\Notification\Messenger\Handler\SendEmailMessageHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

final class MessengerHandlerRegistrationTest extends KernelTestCase
{
    public function testApplicationBusContractsUseMessengerAdapters(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        self::assertInstanceOf(MessengerCommandBus::class, $container->get(CommandBusInterface::class));
        self::assertInstanceOf(MessengerQueryBus::class, $container->get(QueryBusInterface::class));
    }

    public function testCommandBusIsTheDefaultMessengerBus(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        self::assertSame($container->get('command.bus'), $container->get(MessageBusInterface::class));
    }

    public function testEmailMessageHandlerIsRegisteredOnAsyncBus(): void
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);
        $command = $application->find('debug:messenger');
        $tester = new CommandTester($command);
        $tester->execute(['bus' => 'async.bus']);

        $output = $tester->getDisplay();

        self::assertSame(1, substr_count($output, SendEmailMessage::class));
        self::assertSame(1, substr_count($output, SendEmailMessageHandler::class));
    }

    #[DataProvider('signedHandlerProvider')]
    public function testAsynchronousHandlersRequireSignedMessages(string $handlerClass, ?string $method = null): void
    {
        $reflection = new \ReflectionClass($handlerClass);
        $attributes = null === $method
            ? $reflection->getAttributes(AsMessageHandler::class)
            : $reflection->getMethod($method)->getAttributes(AsMessageHandler::class);

        self::assertCount(1, $attributes);
        self::assertTrue($attributes[0]->newInstance()->sign);
    }

    #[DataProvider('busProvider')]
    public function testApplicationHandlersAreRegisteredExactlyOnceOnTheirBus(
        string $busName,
        string $handlerFileSuffix,
        int $expectedCount,
    ): void {
        $kernel = self::bootKernel();
        $application = new Application($kernel);
        $command = $application->find('debug:messenger');
        $tester = new CommandTester($command);
        $tester->execute(['bus' => $busName]);

        $output = $tester->getDisplay();
        $handlerClasses = $this->findHandlerClasses($handlerFileSuffix);

        self::assertCount($expectedCount, $handlerClasses);

        foreach ($handlerClasses as $handlerClass) {
            self::assertSame(
                1,
                substr_count($output, $handlerClass),
                sprintf('Expected handler %s exactly once on %s.', $handlerClass, $busName),
            );
        }

        self::assertStringNotContainsString(SendEmailMessage::class, $output);
    }

    public static function busProvider(): iterable
    {
        yield 'commands' => ['command.bus', 'CommandHandler.php', 32];
        yield 'queries' => ['query.bus', 'QueryHandler.php', 13];
    }

    public static function signedHandlerProvider(): iterable
    {
        yield 'domain event log' => [LogDomainEventHandler::class];
        yield 'disable customer' => [DisableCustomerHandler::class];
        yield 'provision customer after registration' => [ProvisionCustomerHandler::class, 'onUserRegistered'];
        yield 'provision customer after admin creation' => [ProvisionCustomerHandler::class, 'onUserCreatedByAdmin'];
        yield 'revoke sessions' => [RevokeSessionsHandler::class];
        yield 'activation email' => [SendActivationEmailHandler::class];
        yield 'password reset email' => [SendResetPasswordEmailHandler::class];
        yield 'contact email' => [SendEmailMessageHandler::class];
    }

    /**
     * @return list<class-string>
     */
    private function findHandlerClasses(string $fileSuffix): array
    {
        $projectDir = dirname(__DIR__, 4);
        $applicationDir = $projectDir . '/src/Application';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($applicationDir));
        $handlerClasses = [];

        foreach ($iterator as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), $fileSuffix)) {
                continue;
            }

            $relativePath = substr($file->getPathname(), strlen($applicationDir) + 1, -4);
            $handlerClasses[] = 'App\\Application\\' . str_replace('/', '\\', $relativePath);
        }

        sort($handlerClasses);

        return $handlerClasses;
    }
}
