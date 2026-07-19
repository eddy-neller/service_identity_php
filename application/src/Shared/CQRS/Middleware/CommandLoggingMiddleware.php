<?php

declare(strict_types=1);

namespace App\Application\Shared\CQRS\Middleware;

use App\Application\Shared\CQRS\Command\CommandInterface;
use Exception;
use Psr\Log\LoggerInterface;

final readonly class CommandLoggingMiddleware implements CommandMiddlewareInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function handle(CommandInterface $command, callable $next): mixed
    {
        $commandClass = $command::class;
        $startTime = microtime(true);

        $this->logger->info('Dispatching command', [
            'command' => $commandClass,
        ]);

        try {
            $result = $next($command);

            $duration = (microtime(true) - $startTime) * 1000;

            $this->logger->info('Command handled successfully', [
                'command' => $commandClass,
                'duration_ms' => round($duration, 2),
            ]);

            return $result;
        } catch (Exception $exception) {
            $duration = (microtime(true) - $startTime) * 1000;

            $this->logger->error('Command failed', [
                'command' => $commandClass,
                'duration_ms' => round($duration, 2),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
