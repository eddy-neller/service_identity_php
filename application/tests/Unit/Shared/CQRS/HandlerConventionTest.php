<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\Shared\CQRS;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\CQRS\Query\QueryHandlerInterface;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionMethod;
use ReflectionNamedType;
use RegexIterator;

final class HandlerConventionTest extends TestCase
{
    public function testAllCommandsHaveHandlers(): void
    {
        $baseDir = dirname(__DIR__, 4) . '/src';
        $commandFiles = $this->findFiles($baseDir, '/Command\\.php$/');

        foreach ($commandFiles as $file) {
            $commandClass = $this->classFromFile($file, $baseDir, 'App\\Application\\');

            $this->assertTrue(class_exists($commandClass), sprintf('Command class not found for file: %s', $file));

            $handlerClass = preg_replace('/Command$/', 'CommandHandler', $commandClass);

            $this->assertTrue(class_exists($handlerClass), sprintf('Missing handler for command: %s (expected %s)', $commandClass, $handlerClass));

            $this->assertHandlerContract($handlerClass, $commandClass, CommandHandlerInterface::class);
        }
    }

    public function testAllQueriesHaveHandlers(): void
    {
        $baseDir = dirname(__DIR__, 4) . '/src';
        $queryFiles = $this->findFiles($baseDir, '/Query\\.php$/');

        foreach ($queryFiles as $file) {
            $queryClass = $this->classFromFile($file, $baseDir, 'App\\Application\\');

            $this->assertTrue(class_exists($queryClass), sprintf('Query class not found for file: %s', $file));

            $handlerClass = preg_replace('/Query$/', 'QueryHandler', $queryClass);

            $this->assertTrue(class_exists($handlerClass), sprintf('Missing handler for query: %s (expected %s)', $queryClass, $handlerClass));

            $this->assertHandlerContract($handlerClass, $queryClass, QueryHandlerInterface::class);
        }
    }

    public function testEveryCommandHasATest(): void
    {
        $baseDir = dirname(__DIR__, 4) . '/src';
        $commandFiles = $this->findFiles($baseDir, '/Command\\.php$/');

        foreach ($commandFiles as $file) {
            $commandClass = $this->classFromFile($file, $baseDir, 'App\\Application\\');
            $testClass = $this->expectedTestClass($commandClass, 'Command');

            $this->assertTrue(
                class_exists($testClass),
                sprintf('Missing application test for command %s (expected class %s).', $commandClass, $testClass),
            );
        }
    }

    public function testEveryQueryHasATest(): void
    {
        $baseDir = dirname(__DIR__, 4) . '/src';
        $queryFiles = $this->findFiles($baseDir, '/Query\\.php$/');

        foreach ($queryFiles as $file) {
            $queryClass = $this->classFromFile($file, $baseDir, 'App\\Application\\');
            $testClass = $this->expectedTestClass($queryClass, 'Query');

            $this->assertTrue(
                class_exists($testClass),
                sprintf('Missing application test for query %s (expected class %s).', $queryClass, $testClass),
            );
        }
    }

    /**
     * Maps a use case class to its expected unit test class, e.g.
     * App\Application\Shop\UseCase\Command\Catalog\CreateProductByAdmin\CreateProductByAdminCommand
     * → App\Application\Tests\Unit\Shop\UseCase\Command\Catalog\CreateProductByAdminTest.
     */
    private function expectedTestClass(string $useCaseClass, string $suffix): string
    {
        $parts = explode('\\', $useCaseClass);
        $shortName = array_pop($parts);
        array_pop($parts); // drop the use case folder segment (e.g. CreateProductByAdmin)

        $testName = (string) preg_replace('/' . preg_quote($suffix, '/') . '$/', 'Test', $shortName);
        $testNamespace = str_replace('App\\Application\\', 'App\\Application\\Tests\\Unit\\', implode('\\', $parts));

        return $testNamespace . '\\' . $testName;
    }

    /**
     * @return array<int, string>
     */
    private function findFiles(string $baseDir, string $pattern): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseDir)
        );

        $files = new RegexIterator($iterator, $pattern);
        $results = [];

        foreach ($files as $file) {
            $results[] = $file->getPathname();
        }

        return $results;
    }

    private function classFromFile(string $file, string $baseDir, string $baseNamespace): string
    {
        $relative = substr($file, strlen($baseDir) + 1);

        return $baseNamespace . str_replace(
            ['/', '.php'],
            ['\\', ''],
            $relative
        );
    }

    /**
     * @param class-string $handlerClass
     * @param class-string $messageClass
     * @param class-string $handlerInterface
     */
    private function assertHandlerContract(string $handlerClass, string $messageClass, string $handlerInterface): void
    {
        $this->assertTrue(
            is_subclass_of($handlerClass, $handlerInterface),
            sprintf('Handler %s must implement %s.', $handlerClass, $handlerInterface),
        );
        $this->assertTrue(method_exists($handlerClass, 'handle'), sprintf('Handler %s must define handle().', $handlerClass));

        $parameters = (new ReflectionMethod($handlerClass, 'handle'))->getParameters();
        $this->assertCount(1, $parameters, sprintf('Handler %s::handle() must accept exactly one message.', $handlerClass));

        $messageType = $parameters[0]->getType();
        $this->assertInstanceOf(
            ReflectionNamedType::class,
            $messageType,
            sprintf('Handler %s::handle() must type its message.', $handlerClass),
        );
        $this->assertSame(
            $messageClass,
            $messageType->getName(),
            sprintf('Handler %s::handle() must accept %s.', $handlerClass, $messageClass),
        );
    }
}
