<?php

namespace Happy\App\Api\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Happy\Services\Auth\TokenService;
use Happy\Storage\Models\Account;

/**
 * Authentication API controller - handles auth flows.
 */
class AuthController
{
    private TokenService $tokenService;

    public function __construct(TokenService $tokenService)
    {
        $this->tokenService = $tokenService;
    }

    /**
     * Authenticate via signature.
     * POST /v1/auth/signature
     */
    public function signature(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];

        $publicKey = $data['publicKey'] ?? null;
        $signature = $data['signature'] ?? null;
        $timestamp = $data['timestamp'] ?? null;

        if (!$publicKey || !$signature || !$timestamp) {
            $response->getBody()->write(json_encode([
                'error' => 'Missing required fields: publicKey, signature, timestamp'
            ]));
            return $response
                ->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        // Verify timestamp is recent (within 5 minutes)
        $timestampInt = (int)$timestamp;
        $now = time();
        if (abs($now - $timestampInt) > 300) {
            $response->getBody()->write(json_encode([
                'error' => 'Timestamp expired'
            ]));
            return $response
                ->withStatus(401)
                ->withHeader('Content-Type', 'application/json');
        }

        // TODO: Verify signature using sodium_crypto_sign_verify_detached
        // For now, we'll trust the public key and create/find the account

        // Find or create account
        $account = Account::where('public_key', $publicKey)->first();

        if (!$account) {
            $account = Account::create([
                'public_key' => $publicKey,
            ]);
        }

        // Generate token
        $token = $this->tokenService->generatePersistent([
            'userId' => $account->id,
            'publicKey' => $publicKey,
        ]);

        $response->getBody()->write(json_encode([
            'token' => $token,
            'account' => [
                'id' => $account->id,
                'publicKey' => $account->public_key,
                'githubUsername' => $account->github_username,
            ],
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Create auth request (for CLI flow).
     * POST /v1/auth/request
     */
    public function createRequest(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];

        // Create a terminal auth request
        $requestId = bin2hex(random_bytes(16));

        // In a real implementation, store this in the database
        // For now, return a placeholder
        $response->getBody()->write(json_encode([
            'requestId' => $requestId,
            'expiresAt' => date('c', time() + 300), // 5 minutes
        ]));
        return $response
            ->withStatus(201)
            ->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get auth request status.
     * GET /v1/auth/request/{id}
     */
    public function getRequest(Request $request, Response $response, array $args): Response
    {
        $requestId = $args['id'];

        // In a real implementation, look up the request in the database
        // For now, return pending status
        $response->getBody()->write(json_encode([
            'requestId' => $requestId,
            'status' => 'pending',
            'token' => null,
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Respond to auth request (approve from mobile).
     * POST /v1/auth/response
     */
    public function respondToRequest(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];

        $requestId = $data['requestId'] ?? null;
        $approved = $data['approved'] ?? false;

        if (!$requestId) {
            $response->getBody()->write(json_encode([
                'error' => 'requestId is required'
            ]));
            return $response
                ->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        // In a real implementation, update the request in the database
        $response->getBody()->write(json_encode([
            'success' => true,
            'requestId' => $requestId,
            'approved' => $approved,
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
