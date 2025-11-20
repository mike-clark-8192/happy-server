<?php

namespace Happy\App\Api\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Happy\Services\Auth\TokenService;
use Happy\Services\Auth\SignatureService;
use Happy\Storage\Models\Account;
use Happy\Storage\Models\TerminalAuthRequest;
use Happy\Utils\Validator;

/**
 * Authentication API controller - handles auth flows.
 */
class AuthController
{
    private TokenService $tokenService;
    private SignatureService $signatureService;

    public function __construct(TokenService $tokenService, SignatureService $signatureService)
    {
        $this->tokenService = $tokenService;
        $this->signatureService = $signatureService;
    }

    /**
     * Authenticate via signature.
     * POST /v1/auth/signature
     */
    public function signature(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];

        // Validate input
        try {
            Validator::make($data)
                ->required('publicKey')
                ->required('signature')
                ->required('timestamp')
                ->string('publicKey')
                ->string('signature')
                ->validate();
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'error' => $e->getMessage()
            ]));
            return $response
                ->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        $publicKey = $data['publicKey'];
        $signature = $data['signature'];
        $timestamp = $data['timestamp'];

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

        // Verify signature
        try {
            $message = (string)$timestamp;
            $isValid = $this->signatureService->verify($publicKey, $signature, $message);

            if (!$isValid) {
                $response->getBody()->write(json_encode([
                    'error' => 'Invalid signature'
                ]));
                return $response
                    ->withStatus(401)
                    ->withHeader('Content-Type', 'application/json');
            }
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'error' => 'Signature verification failed: ' . $e->getMessage()
            ]));
            return $response
                ->withStatus(401)
                ->withHeader('Content-Type', 'application/json');
        }

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
        $authRequest = new TerminalAuthRequest([
            'id' => TerminalAuthRequest::generateId(),
            'expires_at' => now()->addMinutes(5),
        ]);

        // Store metadata if provided
        if (isset($data['metadata'])) {
            $authRequest->setMetadata($data['metadata']);
        }

        $authRequest->save();

        $response->getBody()->write(json_encode([
            'requestId' => $authRequest->id,
            'expiresAt' => $authRequest->expires_at->toIso8601String(),
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

        $authRequest = TerminalAuthRequest::find($requestId);

        if (!$authRequest) {
            $response->getBody()->write(json_encode([
                'error' => 'Auth request not found'
            ]));
            return $response
                ->withStatus(404)
                ->withHeader('Content-Type', 'application/json');
        }

        // Check if expired
        if ($authRequest->isExpired()) {
            $response->getBody()->write(json_encode([
                'requestId' => $requestId,
                'status' => 'expired',
                'token' => null,
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        // Check if approved
        if ($authRequest->isApproved()) {
            // Get the account and generate token
            $account = $authRequest->responseAccount;
            $token = $this->tokenService->generatePersistent([
                'userId' => $account->id,
                'publicKey' => $account->public_key,
            ]);

            $response->getBody()->write(json_encode([
                'requestId' => $requestId,
                'status' => 'approved',
                'token' => $token,
                'account' => [
                    'id' => $account->id,
                    'publicKey' => $account->public_key,
                    'githubUsername' => $account->github_username,
                ],
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        // Still pending
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
        $user = $request->getAttribute('user');
        $data = $request->getParsedBody() ?? [];

        // Validate input
        try {
            Validator::make($data)
                ->required('requestId')
                ->string('requestId')
                ->validate();
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'error' => $e->getMessage()
            ]));
            return $response
                ->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        $requestId = $data['requestId'];
        $approved = $data['approved'] ?? true;

        $authRequest = TerminalAuthRequest::find($requestId);

        if (!$authRequest) {
            $response->getBody()->write(json_encode([
                'error' => 'Auth request not found'
            ]));
            return $response
                ->withStatus(404)
                ->withHeader('Content-Type', 'application/json');
        }

        if ($authRequest->isExpired()) {
            $response->getBody()->write(json_encode([
                'error' => 'Auth request has expired'
            ]));
            return $response
                ->withStatus(410)
                ->withHeader('Content-Type', 'application/json');
        }

        if ($authRequest->isApproved()) {
            $response->getBody()->write(json_encode([
                'error' => 'Auth request already approved'
            ]));
            return $response
                ->withStatus(409)
                ->withHeader('Content-Type', 'application/json');
        }

        if ($approved && $user) {
            $authRequest->approve($user->id);
            $authRequest->save();
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'requestId' => $requestId,
            'approved' => $approved,
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
