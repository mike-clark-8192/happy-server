<?php

namespace Happy\Storage\Models;

use Happy\Storage\Model;

/**
 * Account model - represents a user account.
 */
class Account extends Model
{
    protected $table = 'accounts';

    protected $fillable = [
        'id',
        'github_username',
        'github_user_id',
        'public_key',
        'profile_encrypted',
        'settings_encrypted',
        'version',
    ];

    protected array $encrypted = [
        'profile_encrypted',
        'settings_encrypted',
    ];

    protected $casts = [
        'version' => 'integer',
    ];

    /**
     * Get the machines for this account.
     */
    public function machines()
    {
        return $this->hasMany(Machine::class, 'account_id');
    }

    /**
     * Get the sessions for this account.
     */
    public function sessions()
    {
        return $this->hasMany(Session::class, 'account_id');
    }

    /**
     * Get the artifacts for this account.
     */
    public function artifacts()
    {
        return $this->hasMany(Artifact::class, 'account_id');
    }

    /**
     * Get profile data (decrypted).
     */
    public function getProfile(): ?array
    {
        return $this->profile_encrypted;
    }

    /**
     * Set profile data (will be encrypted).
     */
    public function setProfile(array $profile): self
    {
        $this->profile_encrypted = $profile;
        return $this;
    }

    /**
     * Get settings data (decrypted).
     */
    public function getSettings(): ?array
    {
        return $this->settings_encrypted;
    }

    /**
     * Set settings data (will be encrypted).
     */
    public function setSettings(array $settings): self
    {
        $this->settings_encrypted = $settings;
        return $this;
    }
}
