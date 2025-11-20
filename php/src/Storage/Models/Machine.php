<?php

namespace Happy\Storage\Models;

use Happy\Storage\Model;

/**
 * Machine model - represents a client device/terminal.
 */
class Machine extends Model
{
    protected $table = 'machines';

    protected $fillable = [
        'id',
        'account_id',
        'name',
        'daemon_state_encrypted',
        'version',
        'last_alive_at',
    ];

    protected array $encrypted = [
        'daemon_state_encrypted',
    ];

    protected $casts = [
        'version' => 'integer',
        'last_alive_at' => 'datetime',
    ];

    /**
     * Get the account that owns this machine.
     */
    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * Get daemon state (decrypted).
     */
    public function getDaemonState(): ?array
    {
        return $this->daemon_state_encrypted;
    }

    /**
     * Set daemon state (will be encrypted).
     */
    public function setDaemonState(array $state): self
    {
        $this->daemon_state_encrypted = $state;
        return $this;
    }

    /**
     * Check if machine is online (active within last 5 minutes).
     */
    public function isOnline(): bool
    {
        if (!$this->last_alive_at) {
            return false;
        }

        return $this->last_alive_at->diffInMinutes(now()) < 5;
    }

    /**
     * Update last alive timestamp.
     */
    public function touch(): bool
    {
        $this->last_alive_at = now();
        return $this->save();
    }

    /**
     * Scope for online machines.
     */
    public function scopeOnline($query)
    {
        return $query->where('last_alive_at', '>', now()->subMinutes(5));
    }
}
