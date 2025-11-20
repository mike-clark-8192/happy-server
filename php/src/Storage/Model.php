<?php

namespace Happy\Storage;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Ramsey\Uuid\Uuid;
use Happy\Services\Encryption\EncryptionService;

/**
 * Base model class with encryption, versioning, and timestamp support.
 * Extends Eloquent Model with Happy Server specific functionality.
 */
abstract class Model extends EloquentModel
{
    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The data type of the primary key.
     */
    protected $keyType = 'string';

    /**
     * The attributes that should be encrypted.
     */
    protected array $encrypted = [];

    /**
     * The encryption service instance.
     */
    protected static ?EncryptionService $encryptionService = null;

    /**
     * Set the encryption service for all models.
     */
    public static function setEncryptionService(EncryptionService $service): void
    {
        self::$encryptionService = $service;
    }

    /**
     * Get the encryption service.
     */
    protected static function getEncryptionService(): EncryptionService
    {
        if (self::$encryptionService === null) {
            throw new \RuntimeException('Encryption service not configured. Call Model::setEncryptionService() first.');
        }

        return self::$encryptionService;
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Auto-generate ID on creation
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = self::generateId();
            }
        });

        // Auto-increment version on update
        static::updating(function ($model) {
            if ($model->hasAttribute('version')) {
                $model->version = ($model->version ?? 0) + 1;
            }
        });
    }

    /**
     * Generate a unique ID (UUID v7 for time-ordering).
     */
    public static function generateId(): string
    {
        return Uuid::uuid7()->toString();
    }

    /**
     * Check if model has a specific attribute.
     */
    protected function hasAttribute(string $key): bool
    {
        return array_key_exists($key, $this->attributes) ||
               in_array($key, $this->fillable) ||
               $this->getConnection()->getSchemaBuilder()->hasColumn($this->getTable(), $key);
    }

    /**
     * Get an attribute from the model.
     * Automatically decrypts encrypted attributes.
     */
    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);

        // Decrypt if this is an encrypted attribute
        if (in_array($key, $this->encrypted) && $value !== null) {
            try {
                $decrypted = self::getEncryptionService()->decrypt(
                    $value,
                    $this->getEncryptionPath($key)
                );
                return json_decode($decrypted, true);
            } catch (\Exception $e) {
                // Return raw value if decryption fails (might be unencrypted)
                return $value;
            }
        }

        return $value;
    }

    /**
     * Set an attribute on the model.
     * Automatically encrypts encrypted attributes.
     */
    public function setAttribute($key, $value)
    {
        // Encrypt if this is an encrypted attribute
        if (in_array($key, $this->encrypted) && $value !== null) {
            $value = self::getEncryptionService()->encrypt(
                json_encode($value),
                $this->getEncryptionPath($key)
            );
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Get the encryption path for an attribute.
     * Override in child classes for custom paths.
     */
    protected function getEncryptionPath(string $attribute): string
    {
        $id = $this->{$this->getKeyName()} ?? 'new';
        return "{$this->getTable()}/{$id}/{$attribute}";
    }

    /**
     * Convert the model to an array.
     * Decrypts encrypted attributes for output.
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        // Ensure encrypted attributes are decrypted in output
        foreach ($this->encrypted as $key) {
            if (isset($array[$key])) {
                $array[$key] = $this->getAttribute($key);
            }
        }

        return $array;
    }

    /**
     * Get the raw (encrypted) value of an attribute.
     */
    public function getRawAttribute(string $key)
    {
        return parent::getAttribute($key);
    }

    /**
     * Set a raw (pre-encrypted) value for an attribute.
     */
    public function setRawAttribute(string $key, $value): self
    {
        return parent::setAttribute($key, $value);
    }
}
