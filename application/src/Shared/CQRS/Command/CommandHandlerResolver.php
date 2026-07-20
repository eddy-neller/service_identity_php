<?php

declare(strict_types=1);

namespace App\Application\Shared\CQRS\Command;

use LogicException;
use Psr\Container\ContainerInterface;

final class CommandHandlerResolver implements CommandHandlerResolverInterface
{
    private array $handlerCache = [];

    public function __construct(
        private readonly ContainerInterface $handlers,
    ) {
    }

    public function resolve(CommandInterface $command): callable
    {
        $commandClass = $command::class;

        if (isset($this->handlerCache[$commandClass])) {
            return $this->handlerCache[$commandClass];
        }

        $handlerClass = $this->discoverHandlerClass($commandClass);

        if (!$this->handlers->has($handlerClass)) {
            throw new LogicException(sprintf('Handler "%s" not found for command "%s". Make sure the handler is registered as a service.', $handlerClass, $commandClass));
        }

        $handler = $this->handlers->get($handlerClass);

        if (!method_exists($handler, 'handle')) {
            throw new LogicException(sprintf('Handler "%s" does not have a "handle" method for command "%s".', $handlerClass, $commandClass));
        }

        $callable = static function (CommandInterface $cmd) use ($handler, $commandClass): mixed {
            if (!$cmd instanceof $commandClass) {
                throw new LogicException(sprintf('Command type mismatch. Expected "%s", got "%s".', $commandClass, $cmd::class));
            }

            return $handler->handle($cmd);
        };

        $this->handlerCache[$commandClass] = $callable;

        return $callable;
    }

    private function discoverHandlerClass(string $commandClass): string
    {
        if (!str_ends_with($commandClass, 'Command')) {
            throw new LogicException(sprintf('Command class "%s" must end with "Command" to use auto-discovery.', $commandClass));
        }

        $handlerClass = preg_replace('/Command$/', 'CommandHandler', $commandClass);

        if (!class_exists($handlerClass)) {
            throw new LogicException(sprintf('Handler class "%s" not found for command "%s". Expected handler class based on convention: {Action}Command → {Action}CommandHandler.', $handlerClass, $commandClass));
        }

        return $handlerClass;
    }
}
