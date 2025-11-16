<?php

namespace Tests\Unit\Services\Auth;

use PHPUnit\Framework\TestCase;
use Happy\Services\Auth\TokenService;

class TokenServiceTest extends TestCase
{
    private TokenService $tokenService;

    protected function setUp(): void
    {
        $this->tokenService = new TokenService('test-jwt-secret-key');
    }

    public function testThrowsExceptionForEmptyJwtSecret(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT secret cannot be empty');
        new TokenService('');
    }

    public function testGeneratePersistentToken(): void
    {
        $payload = ['userId' => '12345', 'email' => 'test@example.com'];
        $token = $this->tokenService->generatePersistent($payload);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        // Verify it's a valid JWT format (3 parts separated by dots)
        $parts = explode('.', $token);
        $this->assertCount(3, $parts);
    }

    public function testGenerateEphemeralToken(): void
    {
        $payload = ['purpose' => 'github-oauth'];
        $token = $this->tokenService->generateEphemeral($payload);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        // Verify it's a valid JWT format
        $parts = explode('.', $token);
        $this->assertCount(3, $parts);
    }

    public function testVerifyPersistentToken(): void
    {
        $payload = ['userId' => '12345', 'role' => 'admin'];
        $token = $this->tokenService->generatePersistent($payload);

        $decoded = $this->tokenService->verify($token);

        $this->assertEquals('12345', $decoded['userId']);
        $this->assertEquals('admin', $decoded['role']);
        $this->assertEquals('persistent', $decoded['type']);
        $this->assertArrayHasKey('iat', $decoded);
        $this->assertArrayHasKey('exp', $decoded);
    }

    public function testVerifyEphemeralToken(): void
    {
        $payload = ['sessionId' => 'abc123'];
        $token = $this->tokenService->generateEphemeral($payload);

        $decoded = $this->tokenService->verify($token);

        $this->assertEquals('abc123', $decoded['sessionId']);
        $this->assertEquals('ephemeral', $decoded['type']);
    }

    public function testVerifyThrowsExceptionForInvalidToken(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid or expired token');
        $this->tokenService->verify('invalid.token.here');
    }

    public function testVerifyThrowsExceptionForTamperedToken(): void
    {
        $token = $this->tokenService->generatePersistent(['userId' => '123']);

        // Tamper with the token
        $parts = explode('.', $token);
        $parts[2] = 'tampered-signature';
        $tamperedToken = implode('.', $parts);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid or expired token');
        $this->tokenService->verify($tamperedToken);
    }

    public function testPersistentTokenHasLongExpiration(): void
    {
        $payload = ['userId' => '123'];
        $token = $this->tokenService->generatePersistent($payload);
        $decoded = $this->tokenService->verify($token);

        $expirationTime = $decoded['exp'] - $decoded['iat'];
        $oneYear = 365 * 24 * 60 * 60;

        $this->assertEquals($oneYear, $expirationTime);
    }

    public function testEphemeralTokenHasShortExpiration(): void
    {
        $payload = ['purpose' => 'test'];
        $token = $this->tokenService->generateEphemeral($payload);
        $decoded = $this->tokenService->verify($token);

        $expirationTime = $decoded['exp'] - $decoded['iat'];
        $fiveMinutes = 5 * 60;

        $this->assertEquals($fiveMinutes, $expirationTime);
    }

    public function testIsExpiredReturnsFalseForValidToken(): void
    {
        $token = $this->tokenService->generatePersistent(['userId' => '123']);
        $this->assertFalse($this->tokenService->isExpired($token));
    }

    public function testIsExpiredReturnsTrueForInvalidToken(): void
    {
        $this->assertTrue($this->tokenService->isExpired('invalid.token.here'));
    }

    public function testDecodeWithoutVerification(): void
    {
        $payload = ['userId' => '123', 'email' => 'test@example.com'];
        $token = $this->tokenService->generatePersistent($payload);

        $decoded = $this->tokenService->decodeWithoutVerification($token);

        $this->assertEquals('123', $decoded['userId']);
        $this->assertEquals('test@example.com', $decoded['email']);
    }

    public function testDecodeWithoutVerificationThrowsForInvalidFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JWT format');
        $this->tokenService->decodeWithoutVerification('not.enough');
    }

    public function testDifferentTokensForSamePayload(): void
    {
        $payload = ['userId' => '123'];

        $token1 = $this->tokenService->generatePersistent($payload);
        sleep(1); // Ensure different iat timestamp
        $token2 = $this->tokenService->generatePersistent($payload);

        $this->assertNotEquals($token1, $token2);

        // But both should verify successfully
        $decoded1 = $this->tokenService->verify($token1);
        $decoded2 = $this->tokenService->verify($token2);

        $this->assertEquals($decoded1['userId'], $decoded2['userId']);
    }

    public function testTokenWithDifferentSecretCannotBeVerified(): void
    {
        $otherService = new TokenService('different-secret');
        $token = $otherService->generatePersistent(['userId' => '123']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid or expired token');
        $this->tokenService->verify($token);
    }

    public function testTokenSupportsComplexPayload(): void
    {
        $payload = [
            'userId' => '12345',
            'email' => 'user@example.com',
            'roles' => ['admin', 'editor'],
            'metadata' => [
                'createdAt' => '2024-01-01',
                'preferences' => ['theme' => 'dark']
            ]
        ];

        $token = $this->tokenService->generatePersistent($payload);
        $decoded = $this->tokenService->verify($token);

        $this->assertEquals($payload['userId'], $decoded['userId']);
        $this->assertEquals($payload['email'], $decoded['email']);
        $this->assertEquals($payload['roles'], $decoded['roles']);
        $this->assertEquals($payload['metadata'], (array)$decoded['metadata']);
    }
}
