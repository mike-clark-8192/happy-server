<?php

namespace Happy\App\Api\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response as SlimResponse;

/**
 * Rate limiting middleware - limits requests per IP.
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    private int $maxRequests;
    private int $windowSeconds;
    private string $cacheDir;
    private array $whitelist;

    public function __construct(
        int $maxRequests = 60,
        int $windowSeconds = 60,
        ?string $cacheDir = null,
        array $whitelist = ['127.0.0.1']
    ) {
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;
        $this->cacheDir = $cacheDir ?? sys_get_temp_dir() . '/happy-rate-limit';
        $this->whitelist = $whitelist;

        // Ensure cache directory exists
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';

        // Skip rate limiting for whitelisted IPs
        if (in_array($ip, $this->whitelist)) {
            return $handler->handle($request);
        }

        // Check rate limit
        $key = $this->getKey($ip);
        $data = $this->getData($key);

        $now = time();
        $windowStart = $now - $this->windowSeconds;

        // Remove old requests
        $data = array_filter($data, fn($timestamp) => $timestamp > $windowStart);

        // Check if over limit
        if (count($data) >= $this->maxRequests) {
            return $this->tooManyRequests($data, $windowStart);
        }

        // Add current request
        $data[] = $now;
        $this->setData($key, $data);

        // Add rate limit headers
        $response = $handler->handle($request);

        return $response
            ->withHeader('X-RateLimit-Limit', (string)$this->maxRequests)
            ->withHeader('X-RateLimit-Remaining', (string)($this->maxRequests - count($data)))
            ->withHeader('X-RateLimit-Reset', (string)($now + $this->windowSeconds));
    }

    private function getKey(string $ip): string
    {
        return md5($ip);
    }

    private function getData(string $key): array
    {
        $file = $this->cacheDir . '/' . $key;

        if (!file_exists($file)) {
            return [];
        }

        $content = file_get_contents($file);
        return json_decode($content, true) ?? [];
    }

    private function setData(string $key, array $data): void
    {
        $file = $this->cacheDir . '/' . $key;
        file_put_contents($file, json_encode($data));
    }

    private function tooManyRequests(array $data, int $windowStart): Response
    {
        $response = new SlimResponse();

        // Calculate retry after
        $oldestRequest = min($data);
        $retryAfter = $oldestRequest + $this->windowSeconds - time();

        $response->getBody()->write(json_encode([
            'error' => 'Too Many Requests',
            'message' => "Rate limit exceeded. Try again in $retryAfter seconds.",
        ]));

        return $response
            ->withStatus(429)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Retry-After', (string)$retryAfter)
            ->withHeader('X-RateLimit-Limit', (string)$this->maxRequests)
            ->withHeader('X-RateLimit-Remaining', '0')
            ->withHeader('X-RateLimit-Reset', (string)(time() + $retryAfter));
    }
}
