<?php

namespace Happy\Services\Auth;

/**
 * Signature verification service using Ed25519.
 * Verifies signatures from clients using Sodium.
 */
class SignatureService
{
    /**
     * Verify an Ed25519 signature.
     *
     * @param string $publicKey Base64-encoded public key
     * @param string $signature Base64-encoded signature
     * @param string $message The message that was signed
     * @return bool True if signature is valid
     * @throws \InvalidArgumentException If inputs are malformed
     */
    public function verify(string $publicKey, string $signature, string $message): bool
    {
        // Decode public key
        $publicKeyBytes = $this->base64Decode($publicKey, 'public key');

        if (strlen($publicKeyBytes) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new \InvalidArgumentException(
                'Invalid public key length: expected ' . SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES .
                ' bytes, got ' . strlen($publicKeyBytes)
            );
        }

        // Decode signature
        $signatureBytes = $this->base64Decode($signature, 'signature');

        if (strlen($signatureBytes) !== SODIUM_CRYPTO_SIGN_BYTES) {
            throw new \InvalidArgumentException(
                'Invalid signature length: expected ' . SODIUM_CRYPTO_SIGN_BYTES .
                ' bytes, got ' . strlen($signatureBytes)
            );
        }

        // Verify signature
        return sodium_crypto_sign_verify_detached(
            $signatureBytes,
            $message,
            $publicKeyBytes
        );
    }

    /**
     * Verify a timestamped signature (for authentication).
     *
     * @param string $publicKey Base64-encoded public key
     * @param string $signature Base64-encoded signature
     * @param int $timestamp Unix timestamp that was signed
     * @param int $toleranceSeconds How old the timestamp can be (default 5 minutes)
     * @return bool True if signature is valid and timestamp is recent
     * @throws \InvalidArgumentException If inputs are malformed
     * @throws \RuntimeException If timestamp is too old
     */
    public function verifyTimestamped(
        string $publicKey,
        string $signature,
        int $timestamp,
        int $toleranceSeconds = 300
    ): bool {
        // Check timestamp is recent
        $now = time();
        if (abs($now - $timestamp) > $toleranceSeconds) {
            throw new \RuntimeException(
                'Timestamp expired: ' . abs($now - $timestamp) . ' seconds old, ' .
                'tolerance is ' . $toleranceSeconds . ' seconds'
            );
        }

        // Verify signature of timestamp string
        return $this->verify($publicKey, $signature, (string)$timestamp);
    }

    /**
     * Generate a key pair for testing.
     *
     * @return array{publicKey: string, secretKey: string} Base64-encoded keys
     */
    public static function generateKeyPair(): array
    {
        $keyPair = sodium_crypto_sign_keypair();

        return [
            'publicKey' => base64_encode(sodium_crypto_sign_publickey($keyPair)),
            'secretKey' => base64_encode(sodium_crypto_sign_secretkey($keyPair)),
        ];
    }

    /**
     * Sign a message for testing.
     *
     * @param string $message The message to sign
     * @param string $secretKey Base64-encoded secret key
     * @return string Base64-encoded signature
     */
    public static function sign(string $message, string $secretKey): string
    {
        $secretKeyBytes = base64_decode($secretKey, true);
        $signature = sodium_crypto_sign_detached($message, $secretKeyBytes);
        return base64_encode($signature);
    }

    /**
     * Decode base64 with error handling.
     *
     * @param string $data Base64-encoded data
     * @param string $name Name for error messages
     * @return string Decoded bytes
     * @throws \InvalidArgumentException If decoding fails
     */
    private function base64Decode(string $data, string $name): string
    {
        // Try standard base64
        $decoded = base64_decode($data, true);

        if ($decoded === false) {
            // Try URL-safe base64
            $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        }

        if ($decoded === false) {
            throw new \InvalidArgumentException("Invalid base64 encoding for $name");
        }

        return $decoded;
    }
}
