<?php

namespace Happy\Storage\Models;

use Happy\Storage\Model;

/**
 * TerminalAuthRequest model - represents a CLI authentication request.
 */
class TerminalAuthRequest extends Model
{
    protected $table = 'terminal_auth_requests';

    protected $fillable = [
        'id',
        'response_account_id',
        'metadata_encrypted',
        'expires_at',
    ];

    protected array $encrypted = [
        'metadata_encrypted',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Get the account that approved this request.
     */
    public function responseAccount()
    {
        return $this->belongsTo(Account::class, 'response_account_id');
    }

    /**
     * Check if request is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at < now();
    }

    /**
     * Check if request is approved.
     */
    public function isApproved(): bool
    {
        return $this->response_account_id !== null;
    }

    /**
     * Get metadata (decrypted).
     */
    public function getMetadata(): ?array
    {
        return $this->metadata_encrypted;
    }

    /**
     * Set metadata (will be encrypted).
     */
    public function setMetadata(array $metadata): self
    {
        $this->metadata_encrypted = $metadata;
        return $this;
    }

    /**
     * Approve request with an account.
     */
    public function approve(string $accountId): self
    {
        $this->response_account_id = $accountId;
        return $this;
    }

    /**
     * Scope for pending (non-expired, non-approved) requests.
     */
    public function scopePending($query)
    {
        return $query->whereNull('response_account_id')
            ->where('expires_at', '>', now());
    }

    /**
     * Clean up expired requests.
     */
    public static function cleanupExpired(): int
    {
        return self::where('expires_at', '<', now())->delete();
    }
}
