<?php

namespace Happy\App\Api\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response as SlimResponse;

/**
 * CORS and security headers middleware.
 */
class CorsMiddleware implements MiddlewareInterface
{
    private array $allowedOrigins;
    private array $allowedMethods;
    private array $allowedHeaders;
    private bool $allowCredentials;

    public function __construct(
        array $allowedOrigins = ['*'],
        array $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        array $allowedHeaders = ['Authorization', 'Content-Type', 'X-Requested-With'],
        bool $allowCredentials = false
    ) {
        $this->allowedOrigins = $allowedOrigins;
        $this->allowedMethods = $allowedMethods;
        $this->allowedHeaders = $allowedHeaders;
        $this->allowCredentials = $allowCredentials;
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        // Handle preflight OPTIONS request
        if ($request->getMethod() === 'OPTIONS') {
            $response = new SlimResponse();
            return $this->addCorsHeaders($request, $response);
        }

        // Handle normal request
        $response = $handler->handle($request);

        // Add CORS headers
        $response = $this->addCorsHeaders($request, $response);

        // Add security headers
        $response = $this->addSecurityHeaders($response);

        return $response;
    }

    private function addCorsHeaders(Request $request, Response $response): Response
    {
        $origin = $request->getHeaderLine('Origin');

        // Determine allowed origin
        if (in_array('*', $this->allowedOrigins)) {
            $allowOrigin = '*';
        } elseif (in_array($origin, $this->allowedOrigins)) {
            $allowOrigin = $origin;
        } else {
            $allowOrigin = $this->allowedOrigins[0] ?? '';
        }

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $allowOrigin)
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
            ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
            ->withHeader('Access-Control-Max-Age', '86400'); // 24 hours

        if ($this->allowCredentials && $allowOrigin !== '*') {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    private function addSecurityHeaders(Response $response): Response
    {
        return $response
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('X-XSS-Protection', '1; mode=block')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('Content-Security-Policy', "default-src 'self'");
    }
}
