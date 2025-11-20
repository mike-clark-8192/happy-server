<?php

namespace Happy\App\Api\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Happy\Services\Logging\Logger;

/**
 * Logging middleware - logs all requests and responses.
 */
class LoggingMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $start = microtime(true);

        // Get request info
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';

        // Get user ID if authenticated
        $user = $request->getAttribute('user');
        $userId = $user ? $user->id : null;

        // Log request
        Logger::info("$method $path", [
            'type' => 'request',
            'method' => $method,
            'path' => $path,
            'ip' => $ip,
            'userId' => $userId,
        ]);

        try {
            // Handle request
            $response = $handler->handle($request);

            // Calculate duration
            $duration = (microtime(true) - $start) * 1000;

            // Log response
            Logger::info("$method $path -> {$response->getStatusCode()}", [
                'type' => 'response',
                'method' => $method,
                'path' => $path,
                'status' => $response->getStatusCode(),
                'duration_ms' => round($duration, 2),
                'userId' => $userId,
            ]);

            return $response;

        } catch (\Exception $e) {
            // Calculate duration
            $duration = (microtime(true) - $start) * 1000;

            // Log error
            Logger::error("$method $path -> Error", [
                'type' => 'error',
                'method' => $method,
                'path' => $path,
                'error' => $e->getMessage(),
                'duration_ms' => round($duration, 2),
                'userId' => $userId,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
