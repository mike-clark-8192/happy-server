<?php

namespace Happy\Services\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Token service for generating and verifying JWT tokens.
 */
class TokenService
{
    private string $jwtSecret;
    private string $algorithm = 'HS256';

    public function __construct(string $jwtSecret)
    {
        if (empty($jwtSecret)) {
            throw new \InvalidArgumentException('JWT secret cannot be empty');
        }
        $this->jwtSecret = $jwtSecret;
    }

    /**
     * Generate a persistent token (long-lived, 365 days).
     *
     * @param array $payload Custom payload data
     * @return string JWT token
     */
    public function generatePersistent(array $payload): string
    {
        $now = time();

        $tokenPayload = array_merge($payload, [
            'iat' => $now,
            'exp' => $now + (365 * 24 * 60 * 60), // 1 year
            'type' => 'persistent'
        ]);

        return JWT::encode($tokenPayload, $this->jwtSecret, $this->algorithm);
    }

    /**
     * Generate an ephemeral token (short-lived, 5 minutes).
     *
     * @param array $payload Custom payload data
     * @return string JWT token
     */
    public function generateEphemeral(array $payload): string
    {
        $now = time();

        $tokenPayload = array_merge($payload, [
            'iat' => $now,
            'exp' => $now + (5 * 60), // 5 minutes
            'type' => 'ephemeral'
        ]);

        return JWT::encode($tokenPayload, $this->jwtSecret, $this->algorithm);
    }

    /**
     * Verify and decode a JWT token.
     *
     * @param string $token The JWT token to verify
     * @return array Decoded payload
     * @throws \Exception If token is invalid or expired
     */
    public function verify(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, $this->algorithm));
            return (array) $decoded;
        } catch (\Exception $e) {
            throw new \RuntimeException('Invalid or expired token: ' . $e->getMessage());
        }
    }

    /**
     * Check if a token is expired without throwing an exception.
     *
     * @param string $token The JWT token to check
     * @return bool True if expired, false otherwise
     */
    public function isExpired(string $token): bool
    {
        try {
            $this->verify($token);
            return false;
        } catch (\Exception $e) {
            return true;
        }
    }

    /**
     * Extract payload without verification (for debugging).
     *
     * @param string $token The JWT token
     * @return array Decoded payload (unverified)
     */
    public function decodeWithoutVerification(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \InvalidArgumentException('Invalid JWT format');
        }

        $payload = base64_decode(strtr($parts[1], '-_', '+/'), true);
        if ($payload === false) {
            throw new \InvalidArgumentException('Invalid base64 encoding in JWT');
        }

        return json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
    }
}
