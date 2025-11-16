# Phase 2: Core API & Data Layer

**Goal**: Implement all database models, core API endpoints, and business logic with transaction support.

## 2.1 Complete Database Models

### Migration System (`src/Storage/Migrations/`)

**Conversion from Prisma to Eloquent Migrations**:

```php
// 001_create_accounts_table.php
Schema::create('accounts', function (Blueprint $table) {
    $table->string('id', 64)->primary(); // cuid2
    $table->string('github_username')->unique();
    $table->string('public_key')->unique();
    $table->text('profile_encrypted')->nullable(); // JSON encrypted
    $table->text('settings_encrypted')->nullable(); // JSON encrypted
    $table->integer('version')->default(0);
    $table->timestamp('created_at');
    $table->timestamp('updated_at');
});

// 002_create_machines_table.php
Schema::create('machines', function (Blueprint $table) {
    $table->string('id', 64)->primary();
    $table->string('account_id', 64);
    $table->string('name');
    $table->text('daemon_state_encrypted')->nullable();
    $table->integer('version')->default(0);
    $table->timestamp('last_alive_at')->nullable();
    $table->timestamp('created_at');
    $table->timestamp('updated_at');

    $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
    $table->index(['account_id', 'last_alive_at']);
});

// 003_create_sessions_table.php
Schema::create('sessions', function (Blueprint $table) {
    $table->string('id', 64)->primary();
    $table->string('account_id', 64);
    $table->string('tag')->nullable(); // For deduplication
    $table->text('metadata_encrypted')->nullable();
    $table->text('state_encrypted')->nullable();
    $table->text('agent_state_encrypted')->nullable();
    $table->integer('version')->default(0);
    $table->timestamp('created_at');
    $table->timestamp('updated_at');

    $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
    $table->unique(['account_id', 'tag']); // Deduplication
    $table->index(['account_id', 'created_at']);
});

// Additional migrations for:
// - session_messages
// - artifacts
// - user_relationships
// - user_feed_items
// - user_kv_store
// - terminal_auth_requests
// - account_auth_requests
// - github_oauth_tokens
// - service_account_tokens
// - push_tokens
// - voice_settings
// - global_locks
// - repeat_keys
// - simple_cache
// - usage_reports
```

### Model Base Class (`src/Storage/Models/Model.php`)

**Features**:
- Automatic timestamp handling
- Version control support
- Encrypted attribute accessors
- Soft deletes support
- Query scopes

```php
abstract class Model extends \Illuminate\Database\Eloquent\Model {
    protected EncryptionService $encryption;

    // Attributes that should be encrypted
    protected array $encrypted = [];

    // Encryption path for this model
    protected function getEncryptionPath(string $field): string {
        return "model/{$this->getTable()}/{$this->id}/{$field}";
    }

    // Auto-encrypt on set
    public function setAttribute($key, $value) {
        if (in_array($key, $this->encrypted)) {
            $value = $this->encryption->encrypt(
                json_encode($value),
                $this->getEncryptionPath($key)
            );
        }
        return parent::setAttribute($key, $value);
    }

    // Auto-decrypt on get
    public function getAttribute($key) {
        $value = parent::getAttribute($key);
        if (in_array($key, $this->encrypted) && $value) {
            return json_decode(
                $this->encryption->decrypt($value, $this->getEncryptionPath($key)),
                true
            );
        }
        return $value;
    }

    // Version control: increment on update
    protected static function boot() {
        parent::boot();
        static::updating(function ($model) {
            $model->version++;
        });
    }
}
```

### All 15+ Models

**Priority Order**:

1. **Account** - Core user model
2. **Machine** - User devices
3. **Session** - Conversations
4. **SessionMessage** - Chat messages
5. **Artifact** - File attachments
6. **UserRelationship** - Social connections
7. **UserFeedItem** - Activity feed
8. **UserKVStore** - Key-value storage
9. **TerminalAuthRequest** - CLI auth
10. **AccountAuthRequest** - Mobile auth
11. **GithubOAuthToken** - GitHub integration
12. **ServiceAccountToken** - API tokens
13. **PushToken** - Mobile push notifications
14. **VoiceSetting** - Voice preferences
15. **RepeatKey** - Deduplication utility
16. **SimpleCache** - Caching utility
17. **GlobalLock** - Distributed locking
18. **UsageReport** - Metrics tracking

## 2.2 Transaction Support

### Transaction Wrapper (`src/Storage/Transaction.php`)

**Features**:
- Automatic retry on conflicts (SQLite busy/locked)
- Serializable isolation level
- Post-commit callback support
- Timeout handling (10 seconds)

```php
class Transaction {
    private static array $afterCommitCallbacks = [];

    public static function run(callable $callback, int $maxRetries = 3) {
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                DB::beginTransaction();

                // Set serializable isolation for SQLite
                DB::statement('PRAGMA read_uncommitted = 0');

                $result = $callback();

                DB::commit();

                // Execute after-commit callbacks
                foreach (self::$afterCommitCallbacks as $cb) {
                    $cb();
                }
                self::$afterCommitCallbacks = [];

                return $result;

            } catch (\Exception $e) {
                DB::rollBack();
                self::$afterCommitCallbacks = [];

                // Retry on SQLite busy/locked errors
                if (str_contains($e->getMessage(), 'database is locked') && $attempt < $maxRetries - 1) {
                    $attempt++;
                    usleep(100000 * $attempt); // Exponential backoff
                    continue;
                }

                throw $e;
            }
        }
    }

    public static function afterCommit(callable $callback): void {
        self::$afterCommitCallbacks[] = $callback;
    }
}
```

## 2.3 Core API Endpoints

### Session Management (`src/App/Api/Routes/sessions.php`)

**Endpoints**:

1. **POST /v1/sessions** - Create or get session by tag
   - Idempotent with unique(accountId, tag)
   - Returns existing if tag matches

2. **GET /v1/sessions** - List user sessions
   - Cursor-based pagination
   - Sorted by createdAt DESC

3. **GET /v1/sessions/:id** - Get single session
   - Returns metadata, state, agentState

4. **PUT /v1/sessions/:id** - Update session
   - Version-controlled updates
   - Encrypt metadata/state/agentState

5. **DELETE /v1/sessions/:id** - Delete session
   - Cascade delete messages & artifacts
   - Transaction-wrapped

6. **GET /v1/sessions/:id/messages** - Get session messages
   - Pagination with cursor
   - Optional limit

**Implementation Example**:
```php
class SessionController {
    public function create(Request $request, Response $response): Response {
        $data = $request->getParsedBody();
        $user = $request->getAttribute('user');

        return Transaction::run(function() use ($user, $data) {
            // Upsert pattern for idempotency
            $session = Session::firstOrCreate(
                [
                    'account_id' => $user->id,
                    'tag' => $data['tag'] ?? null
                ],
                [
                    'id' => $this->generateId(),
                    'metadata_encrypted' => $data['metadata'] ?? null,
                    'state_encrypted' => $data['state'] ?? null
                ]
            );

            // Emit event after commit
            Transaction::afterCommit(function() use ($session) {
                EventBus::emit('new-session', $session->toArray());
            });

            return $session;
        });
    }

    public function list(Request $request, Response $response): Response {
        $user = $request->getAttribute('user');
        $cursor = $request->getQueryParams()['cursor'] ?? null;
        $limit = (int)($request->getQueryParams()['limit'] ?? 50);

        $query = Session::where('account_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit + 1);

        if ($cursor) {
            $query->where('created_at', '<', $cursor);
        }

        $sessions = $query->get();

        $hasMore = $sessions->count() > $limit;
        if ($hasMore) {
            $sessions->pop();
        }

        return $response->withJson([
            'sessions' => $sessions,
            'nextCursor' => $hasMore ? $sessions->last()->created_at : null
        ]);
    }

    public function delete(Request $request, Response $response, array $args): Response {
        $sessionId = $args['id'];
        $user = $request->getAttribute('user');

        return Transaction::run(function() use ($sessionId, $user) {
            $session = Session::where('id', $sessionId)
                ->where('account_id', $user->id)
                ->firstOrFail();

            // Cascade delete (manual since SQLite doesn't always trigger)
            SessionMessage::where('session_id', $sessionId)->delete();
            Artifact::where('session_id', $sessionId)->delete();
            $session->delete();

            Transaction::afterCommit(function() use ($sessionId) {
                EventBus::emit('delete-session', ['sessionId' => $sessionId]);
            });

            return ['success' => true];
        });
    }
}
```

### Machine Management (`src/App/Api/Routes/machines.php`)

**Endpoints**:

1. **POST /v1/machines** - Register/update machine
   - Upsert pattern
   - Update lastAliveAt timestamp

2. **GET /v1/machines** - List user machines
   - Filter by online status

3. **GET /v1/machines/:id** - Get single machine

### Artifact Management (`src/App/Api/Routes/artifacts.php`)

**Endpoints**:

1. **POST /v1/artifacts** - Create artifact
2. **GET /v1/artifacts/:id** - Get artifact
3. **PUT /v1/artifacts/:id** - Update artifact
4. **DELETE /v1/artifacts/:id** - Delete artifact
5. **GET /v1/sessions/:sessionId/artifacts** - List session artifacts

### Account Management (`src/App/Api/Routes/account.php`)

**Endpoints**:

1. **GET /v1/account** - Get account profile
2. **PUT /v1/account** - Update account settings
3. **POST /v1/account/signature** - Verify ownership

## 2.4 KV Store Implementation

### Atomic Batch Mutations (`src/App/Api/Routes/kv.php`)

**Features**:
- All-or-nothing batch updates
- Version control per key
- Encrypted values
- Atomic increment/decrement

**Endpoints**:

1. **POST /v1/kv/batch** - Batch mutations
   ```json
   {
     "mutations": [
       { "key": "counter", "operation": "increment", "value": 1 },
       { "key": "data", "operation": "set", "value": {...} }
     ]
   }
   ```

2. **GET /v1/kv/:key** - Get value
3. **DELETE /v1/kv/:key** - Delete key
4. **GET /v1/kv** - List all keys

**Implementation**:
```php
class KVController {
    public function batch(Request $request, Response $response): Response {
        $data = $request->getParsedBody();
        $user = $request->getAttribute('user');

        return Transaction::run(function() use ($user, $data) {
            foreach ($data['mutations'] as $mutation) {
                $this->applyMutation($user->id, $mutation);
            }

            return ['success' => true];
        });
    }

    private function applyMutation(string $userId, array $mutation): void {
        $key = $mutation['key'];
        $operation = $mutation['operation'];

        $kv = UserKVStore::firstOrNew([
            'user_id' => $userId,
            'key' => $key
        ]);

        switch ($operation) {
            case 'set':
                $kv->value_encrypted = $mutation['value'];
                break;
            case 'increment':
                $current = $kv->value_encrypted ?? 0;
                $kv->value_encrypted = $current + $mutation['value'];
                break;
            case 'delete':
                $kv->delete();
                return;
        }

        $kv->save();
    }
}
```

## 2.5 Social Features

### User Relationships (`src/App/Api/Routes/social.php`)

**Endpoints**:

1. **POST /v1/relationships** - Send friend request
   - Create or update relationship
   - Status: pending, accepted, declined, removed, blocked

2. **PUT /v1/relationships/:id** - Accept/decline request
3. **DELETE /v1/relationships/:id** - Remove friend
4. **GET /v1/relationships** - List relationships
   - Filter by status

5. **GET /v1/friends** - List accepted friends
6. **GET /v1/profile/:username** - Get user profile

**Friend Request Logic**:
```php
class RelationshipController {
    public function create(Request $request, Response $response): Response {
        $data = $request->getParsedBody();
        $user = $request->getAttribute('user');
        $targetUsername = $data['username'];

        return Transaction::run(function() use ($user, $targetUsername) {
            $target = Account::where('github_username', $targetUsername)->firstOrFail();

            // Create or update relationship
            $relationship = UserRelationship::updateOrCreate(
                [
                    'from_user_id' => $user->id,
                    'to_user_id' => $target->id
                ],
                [
                    'id' => $this->generateId(),
                    'status' => 'pending',
                    'metadata_encrypted' => json_encode(['requestedAt' => time()])
                ]
            );

            // Check cooldown before sending notification
            $lastNotification = $this->getLastNotificationTime($user->id, $target->id);
            if (!$lastNotification || (time() - $lastNotification) > 86400) {
                Transaction::afterCommit(function() use ($relationship, $target) {
                    // Send push notification
                    NotificationService::sendFriendRequest($target->id, $relationship);
                });
            }

            return $relationship;
        });
    }
}
```

### User Feed (`src/App/Api/Routes/feed.php`)

**Endpoints**:

1. **GET /v1/feed** - Get user feed
   - Cursor-based pagination
   - Deduplication with repeatKey

2. **POST /v1/feed** - Add feed item
   - Auto-deduplicate

**Implementation**:
```php
class FeedController {
    public function list(Request $request, Response $response): Response {
        $user = $request->getAttribute('user');
        $cursor = $request->getQueryParams()['cursor'] ?? null;
        $limit = (int)($request->getQueryParams()['limit'] ?? 50);

        $query = UserFeedItem::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit + 1);

        if ($cursor) {
            $query->where('created_at', '<', $cursor);
        }

        $items = $query->get();

        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items->pop();
        }

        return $response->withJson([
            'items' => $items,
            'nextCursor' => $hasMore ? $items->last()->created_at : null
        ]);
    }
}
```

## 2.6 Utilities

### ID Generation (`src/Utils/Id.php`)
```php
class Id {
    public static function generate(): string {
        // CUID2 alternative: Use UUID v7 (timestamp-based)
        return str_replace('-', '', Uuid::uuid7()->toString());
    }
}
```

### Repeat Key (`src/Utils/RepeatKey.php`)
```php
class RepeatKey {
    public static function check(string $domain, string $key, int $ttl = 3600): bool {
        $existing = RepeatKey::where('domain', $domain)
            ->where('key', $key)
            ->where('expires_at', '>', now())
            ->first();

        if ($existing) {
            return false; // Duplicate
        }

        RepeatKey::create([
            'domain' => $domain,
            'key' => $key,
            'expires_at' => now()->addSeconds($ttl)
        ]);

        return true; // Allowed
    }
}
```

### Simple Cache (`src/Utils/Cache.php`)
```php
class Cache {
    public static function get(string $key): mixed {
        $cached = SimpleCache::where('key', $key)
            ->where('expires_at', '>', now())
            ->first();

        return $cached ? json_decode($cached->value, true) : null;
    }

    public static function set(string $key, mixed $value, int $ttl = 3600): void {
        SimpleCache::updateOrCreate(
            ['key' => $key],
            [
                'value' => json_encode($value),
                'expires_at' => now()->addSeconds($ttl)
            ]
        );
    }
}
```

---

## Phase 2 Deliverables

### ✅ Completion Criteria
1. [ ] All 15+ database models created and migrated
2. [ ] Session CRUD endpoints working
3. [ ] Machine registration and listing working
4. [ ] Artifact management working
5. [ ] KV store batch mutations working
6. [ ] Social features (friend requests, feed) working
7. [ ] Transaction support with auto-retry
8. [ ] Version control on all models
9. [ ] Encrypted fields auto-encrypt/decrypt
10. [ ] Cursor-based pagination working
11. [ ] All integration tests pass

### 📦 Files Created (~50 files)
- 17 migration files
- 17 model classes
- 10+ controller classes
- Transaction wrapper
- Utility classes (ID, RepeatKey, Cache)
- Route definitions
- Integration tests

### 🎯 What's Working
- Full CRUD on sessions, machines, artifacts
- Social features: friend requests, relationships
- User feed with deduplication
- KV store with atomic operations
- Encrypted data storage
- Version control and optimistic locking
- Pagination for all list endpoints

---

## Phase 2 Timeline Estimate

**Total**: 5-7 days of focused development

- Day 1: Database migrations (all 17 tables)
- Day 2: Model classes with encryption
- Day 3: Session & Machine controllers
- Day 4: Artifact & KV controllers
- Day 5: Social features (relationships, feed)
- Day 6: Utilities and helpers
- Day 7: Integration testing & debugging

---

## Next: Phase 3

Phase 3 will implement:
- Real-time updates (WebSockets or SSE)
- GitHub OAuth integration
- Push notification system
- Voice settings
- Usage metrics and monitoring
- Deployment scripts for VPS
- Migration tool from TypeScript to PHP
- Final cleanup and documentation
