<?php

namespace Happy\Services\Encryption;

/**
 * Encryption service using Sodium for path-based encryption.
 * Compatible with privacy-kit encryption from the TypeScript version.
 */
class EncryptionService
{
    private string $masterSecret;

    public function __construct(string $masterSecret)
    {
        if (empty($masterSecret)) {
            throw new \InvalidArgumentException('Master secret cannot be empty');
        }
        $this->masterSecret = $masterSecret;
    }

    /**
     * Derive a key from a path using BLAKE2b.
     *
     * @param string $path The path for key derivation
     * @return string 32-byte key
     */
    public function deriveKey(string $path): string
    {
        return sodium_crypto_generichash($path, $this->masterSecret, 32);
    }

    /**
     * Encrypt plaintext data for a given path.
     *
     * @param string $plaintext The data to encrypt
     * @param string $path The path for key derivation
     * @return string Base64-encoded ciphertext with nonce
     */
    public function encrypt(string $plaintext, string $path): string
    {
        $key = $this->deriveKey($path);
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key);

        return base64_encode($nonce . $ciphertext);
    }

    /**
     * Decrypt ciphertext for a given path.
     *
     * @param string $encrypted Base64-encoded ciphertext with nonce
     * @param string $path The path for key derivation
     * @return string Decrypted plaintext
     * @throws \RuntimeException If decryption fails
     */
    public function decrypt(string $encrypted, string $path): string
    {
        $key = $this->deriveKey($path);
        $decoded = base64_decode($encrypted, true);

        if ($decoded === false) {
            throw new \RuntimeException('Invalid base64 encoding');
        }

        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);

        if ($plaintext === false) {
            throw new \RuntimeException('Decryption failed - invalid key or corrupted data');
        }

        return $plaintext;
    }

    /**
     * Encrypt JSON data.
     *
     * @param mixed $data The data to JSON encode and encrypt
     * @param string $path The path for key derivation
     * @return string Base64-encoded encrypted JSON
     */
    public function encryptJson(mixed $data, string $path): string
    {
        $json = json_encode($data, JSON_THROW_ON_ERROR);
        return $this->encrypt($json, $path);
    }

    /**
     * Decrypt JSON data.
     *
     * @param string $encrypted Base64-encoded encrypted JSON
     * @param string $path The path for key derivation
     * @return mixed Decoded JSON data
     */
    public function decryptJson(string $encrypted, string $path): mixed
    {
        $json = $this->decrypt($encrypted, $path);
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }
}
