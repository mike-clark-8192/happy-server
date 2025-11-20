<?php

namespace Happy\App\Api\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Happy\Services\Auth\TokenService;
use Happy\Storage\Models\Account;
use Slim\Psr7\Response as SlimResponse;

/**
 * Authentication middleware - validates JWT tokens and injects user into request.
 */
class AuthMiddleware implements MiddlewareInterface
{
    private TokenService $tokenService;

    public function __construct(TokenService $tokenService)
    {
        $this->tokenService = $tokenService;
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        // Extract token from Authorization header
        $authHeader = $request->getHeaderLine('Authorization');

        if (empty($authHeader)) {
            return $this->unauthorized('Missing Authorization header');
        }

        // Parse Bearer token
        if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return $this->unauthorized('Invalid Authorization header format');
        }

        $token = $matches[1];

        try {
            // Verify token
            $payload = $this->tokenService->verify($token);

            // Get user ID from token
            $userId = $payload['userId'] ?? $payload['sub'] ?? null;

            if (empty($userId)) {
                return $this->unauthorized('Invalid token payload');
            }

            // Load account
            $account = Account::find($userId);

            if (!$account) {
                return $this->unauthorized('Account not found');
            }

            // Inject user into request
            $request = $request->withAttribute('user', $account);
            $request = $request->withAttribute('token', $payload);

            return $handler->handle($request);

        } catch (\Exception $e) {
            return $this->unauthorized('Invalid or expired token');
        }
    }

    private function unauthorized(string $message): Response
    {
        $response = new SlimResponse();
        $response->getBody()->write(json_encode([
            'error' => 'Unauthorized',
            'message' => $message,
        ]));

        return $response
            ->withStatus(401)
            ->withHeader('Content-Type', 'application/json');
    }
}
