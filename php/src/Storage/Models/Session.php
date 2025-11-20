<?php

namespace Happy\Storage\Models;

use Happy\Storage\Model;

/**
 * Session model - represents a conversation session.
 */
class Session extends Model
{
    protected $table = 'sessions';

    protected $fillable = [
        'id',
        'account_id',
        'tag',
        'metadata_encrypted',
        'state_encrypted',
        'agent_state_encrypted',
        'version',
        'sort_key',
    ];

    protected array $encrypted = [
        'metadata_encrypted',
        'state_encrypted',
        'agent_state_encrypted',
    ];

    protected $casts = [
        'version' => 'integer',
        'sort_key' => 'integer',
    ];

    /**
     * Get the account that owns this session.
     */
    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * Get the messages for this session.
     */
    public function messages()
    {
        return $this->hasMany(SessionMessage::class, 'session_id')->orderBy('seq');
    }

    /**
     * Get the artifacts for this session.
     */
    public function artifacts()
    {
        return $this->hasMany(Artifact::class, 'session_id');
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
     * Get state (decrypted).
     */
    public function getState(): ?array
    {
        return $this->state_encrypted;
    }

    /**
     * Set state (will be encrypted).
     */
    public function setState(array $state): self
    {
        $this->state_encrypted = $state;
        return $this;
    }

    /**
     * Get agent state (decrypted).
     */
    public function getAgentState(): ?array
    {
        return $this->agent_state_encrypted;
    }

    /**
     * Set agent state (will be encrypted).
     */
    public function setAgentState(array $agentState): self
    {
        $this->agent_state_encrypted = $agentState;
        return $this;
    }

    /**
     * Delete session with all related data.
     */
    public function deleteWithRelations(): bool
    {
        // Delete messages
        $this->messages()->delete();

        // Delete artifacts
        $this->artifacts()->delete();

        // Delete session
        return $this->delete();
    }
}
