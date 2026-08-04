<?php

declare(strict_types=1);

namespace App\Infrastructure\Tests\Unit\Command\CQRS;

use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\Shared\Messenger\Message\SendEmailMessage;
use App\Infrastructure\Messenger\CQRS\MessengerCommandBus;
use App\Infrastructure\Messenger\CQRS\MessengerQueryBus;
use App\Infrastructure\Notification\Messenger\Handler\SendEmailMessageHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
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

    /**
     * @return list<class-string>
     */
    private function findHandlerClasses(string $fileSuffix): array
    {
        $projectDir = dirname(__DIR__, 5);
        $applicationDir = $projectDir . '/application/src';
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
