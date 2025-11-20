<?php

namespace Happy\Storage\Models;

use Happy\Storage\Model;

/**
 * Artifact model - represents a file/document attached to a session.
 */
class Artifact extends Model
{
    protected $table = 'artifacts';

    protected $fillable = [
        'id',
        'session_id',
        'account_id',
        'content_encrypted',
        'metadata_encrypted',
        'version',
    ];

    protected array $encrypted = [
        'content_encrypted',
        'metadata_encrypted',
    ];

    protected $casts = [
        'version' => 'integer',
    ];

    /**
     * Get the session this artifact belongs to.
     */
    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id');
    }

    /**
     * Get the account that owns this artifact.
     */
    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * Get content (decrypted).
     */
    public function getContent(): ?array
    {
        return $this->content_encrypted;
    }

    /**
     * Set content (will be encrypted).
     */
    public function setContent(array $content): self
    {
        $this->content_encrypted = $content;
        return $this;
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
}
