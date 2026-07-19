<?php

declare(strict_types=1);

namespace App\Application\Shared\CQRS\Middleware;

use App\Application\Shared\CQRS\Query\QueryInterface;
use Exception;
use Psr\Log\LoggerInterface;

final readonly class QueryLoggingMiddleware implements QueryMiddlewareInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function handle(QueryInterface $query, callable $next): mixed
    {
        $queryClass = $query::class;
        $startTime = microtime(true);

        $this->logger->info('Dispatching query', [
            'query' => $queryClass,
        ]);

        try {
            $result = $next($query);

            $duration = (microtime(true) - $startTime) * 1000;

            $this->logger->info('Query handled successfully', [
                'query' => $queryClass,
                'duration_ms' => round($duration, 2),
            ]);

            return $result;
        } catch (Exception $exception) {
            $duration = (microtime(true) - $startTime) * 1000;

            $this->logger->error('Query failed', [
                'query' => $queryClass,
                'duration_ms' => round($duration, 2),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
