<?php

namespace Tests\Unit\Services\Encryption;

use PHPUnit\Framework\TestCase;
use Happy\Services\Encryption\EncryptionService;

class EncryptionServiceTest extends TestCase
{
    private EncryptionService $encryption;

    protected function setUp(): void
    {
        $this->encryption = new EncryptionService('test-master-secret-32-bytes-long');
    }

    public function testThrowsExceptionForEmptyMasterSecret(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Master secret cannot be empty');
        new EncryptionService('');
    }

    public function testDeriveKeyProduces32ByteKey(): void
    {
        $key = $this->encryption->deriveKey('test/path');
        $this->assertEquals(32, strlen($key));
    }

    public function testDeriveKeyProducesDifferentKeysForDifferentPaths(): void
    {
        $key1 = $this->encryption->deriveKey('path/one');
        $key2 = $this->encryption->deriveKey('path/two');

        $this->assertNotEquals($key1, $key2);
    }

    public function testDeriveKeyProducesSameKeyForSamePath(): void
    {
        $key1 = $this->encryption->deriveKey('same/path');
        $key2 = $this->encryption->deriveKey('same/path');

        $this->assertEquals($key1, $key2);
    }

    public function testEncryptAndDecryptPlaintext(): void
    {
        $plaintext = 'Hello, World!';
        $path = 'test/message';

        $encrypted = $this->encryption->encrypt($plaintext, $path);
        $decrypted = $this->encryption->decrypt($encrypted, $path);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function testEncryptedDataIsBase64Encoded(): void
    {
        $plaintext = 'Test data';
        $encrypted = $this->encryption->encrypt($plaintext, 'test/path');

        // Should be valid base64
        $decoded = base64_decode($encrypted, true);
        $this->assertNotFalse($decoded);
    }

    public function testEncryptedDataIsDifferentEachTime(): void
    {
        $plaintext = 'Same data';
        $path = 'test/path';

        $encrypted1 = $this->encryption->encrypt($plaintext, $path);
        $encrypted2 = $this->encryption->encrypt($plaintext, $path);

        // Different nonces should produce different ciphertext
        $this->assertNotEquals($encrypted1, $encrypted2);

        // But both should decrypt to the same plaintext
        $this->assertEquals($plaintext, $this->encryption->decrypt($encrypted1, $path));
        $this->assertEquals($plaintext, $this->encryption->decrypt($encrypted2, $path));
    }

    public function testDecryptFailsWithWrongPath(): void
    {
        $plaintext = 'Secret data';
        $encrypted = $this->encryption->encrypt($plaintext, 'correct/path');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Decryption failed');
        $this->encryption->decrypt($encrypted, 'wrong/path');
    }

    public function testDecryptFailsWithInvalidBase64(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid base64 encoding');
        $this->encryption->decrypt('not-valid-base64!!!', 'test/path');
    }

    public function testDecryptFailsWithCorruptedData(): void
    {
        $plaintext = 'Test data';
        $encrypted = $this->encryption->encrypt($plaintext, 'test/path');

        // Corrupt the encrypted data
        $decoded = base64_decode($encrypted);
        $corrupted = base64_encode($decoded . 'corrupted');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Decryption failed');
        $this->encryption->decrypt($corrupted, 'test/path');
    }

    public function testEncryptAndDecryptJson(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
            'active' => true
        ];
        $path = 'user/profile';

        $encrypted = $this->encryption->encryptJson($data, $path);
        $decrypted = $this->encryption->decryptJson($encrypted, $path);

        $this->assertEquals($data, $decrypted);
    }

    public function testEncryptJsonHandlesNestedObjects(): void
    {
        $data = [
            'user' => [
                'name' => 'Jane',
                'preferences' => [
                    'theme' => 'dark',
                    'notifications' => true
                ]
            ]
        ];

        $encrypted = $this->encryption->encryptJson($data, 'user/settings');
        $decrypted = $this->encryption->decryptJson($encrypted, 'user/settings');

        $this->assertEquals($data, $decrypted);
    }

    public function testEncryptJsonHandlesUnicodeCharacters(): void
    {
        $data = [
            'message' => 'Hello 世界 🌍',
            'name' => 'José María'
        ];

        $encrypted = $this->encryption->encryptJson($data, 'test/unicode');
        $decrypted = $this->encryption->decryptJson($encrypted, 'test/unicode');

        $this->assertEquals($data, $decrypted);
    }

    public function testEncryptWorksWithEmptyString(): void
    {
        $plaintext = '';
        $encrypted = $this->encryption->encrypt($plaintext, 'test/empty');
        $decrypted = $this->encryption->decrypt($encrypted, 'test/empty');

        $this->assertEquals($plaintext, $decrypted);
    }

    public function testEncryptWorksWithLargeData(): void
    {
        $plaintext = str_repeat('A', 10000); // 10KB of data
        $encrypted = $this->encryption->encrypt($plaintext, 'test/large');
        $decrypted = $this->encryption->decrypt($encrypted, 'test/large');

        $this->assertEquals($plaintext, $decrypted);
    }
}
