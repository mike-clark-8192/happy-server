<?php

namespace Happy\Storage\Models;

use Happy\Storage\Model;

/**
 * SessionMessage model - represents a message in a session.
 */
class SessionMessage extends Model
{
    protected $table = 'session_messages';

    protected $fillable = [
        'id',
        'session_id',
        'local_id',
        'content_encrypted',
        'seq',
    ];

    protected array $encrypted = [
        'content_encrypted',
    ];

    protected $casts = [
        'seq' => 'integer',
    ];

    /**
     * Get the session this message belongs to.
     */
    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id');
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
}
