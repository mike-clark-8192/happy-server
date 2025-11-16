# Phase 1: Foundation & Core Infrastructure

**Goal**: Establish the foundational PHP architecture with authentication, encryption, and basic routing.

## 1.1 Project Setup & Structure

### Composer Configuration (`composer.json`)
```json
{
    "name": "happy/server",
    "description": "Happy Server - PHP Implementation",
    "type": "project",
    "license": "MIT",
    "require": {
        "php": "^8.2",
        "slim/slim": "^4.13",
        "slim/psr7": "^1.6",
        "php-di/php-di": "^7.0",
        "illuminate/database": "^11.0",
        "paragonie/sodium_compat": "^2.0",
        "firebase/php-jwt": "^6.10",
        "vlucas/phpdotenv": "^5.6",
        "monolog/monolog": "^3.5",
        "guzzlehttp/guzzle": "^7.8",
        "ramsey/uuid": "^4.7"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.5",
        "phpstan/phpstan": "^1.10"
    },
    "autoload": {
        "psr-4": {
            "Happy\\": "src/"
        }
    }
}
```

### Directory Structure
```
happy-server-php/
├── composer.json
├── .env.example
├── public/
│   └── index.php           # Entry point
├── src/
│   ├── App/
│   │   ├── Api/
│   │   │   ├── Middleware/
│   │   │   └── Routes/
│   │   └── Events/
│   ├── Storage/
│   │   ├── Database.php
│   │   ├── Models/
│   │   └── Migrations/
│   ├── Services/
│   │   ├── Auth/
│   │   ├── Encryption/
│   │   └── Token/
│   ├── Utils/
│   └── bootstrap.php
├── storage/
│   ├── database/
│   │   └── database.sqlite
│   ├── logs/
│   └── cache/
└── tests/
```

## 1.2 Database Layer with SQLite

### Database Connection (`src/Storage/Database.php`)
**Technology**: Illuminate/Database (Laravel's Eloquent ORM - lightweight & proven)

**Features**:
- SQLite connection with WAL mode for concurrent reads
- Query builder with parameter binding
- Transaction support with serializable isolation
- Model base class with soft deletes, timestamps

**Schema Migration Strategy**:
- Convert Prisma schema to SQLite-compatible migrations
- Use Illuminate's Schema Builder
- Version control with migration tracking table

**Key Differences from PostgreSQL**:
- No native JSON operators (use JSON functions)
- No native UUID type (use TEXT with validation)
- Manual foreign key cascade handling
- DATETIME instead of TIMESTAMP

### Core Models to Implement
Priority order:
1. **Account** - Users and authentication
2. **Machine** - Client devices/terminals
3. **Session** - Core conversation sessions
4. **SessionMessage** - Message history
5. **Artifact** - File attachments

## 1.3 Security & Encryption

### Encryption Service (`src/Services/Encryption/EncryptionService.php`)

**Technology**: Sodium (libsodium via `sodium_compat`)

**Features**:
- Path-based key derivation (matches privacy-kit)
- Symmetric encryption with XSalsa20-Poly1305
- Base64 encoding/decoding for storage
- Master secret from environment

**Implementation**:
```php
class EncryptionService {
    private string $masterSecret;

    public function deriveKey(string $path): string {
        // Use BLAKE2b for key derivation
        return sodium_crypto_generichash($path, $this->masterSecret, 32);
    }

    public function encrypt(string $plaintext, string $path): string {
        $key = $this->deriveKey($path);
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key);
        return base64_encode($nonce . $ciphertext);
    }

    public function decrypt(string $encrypted, string $path): string {
        $key = $this->deriveKey($path);
        $decoded = base64_decode($encrypted);
        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
    }
}
```

### Signature Verification (`src/Services/Auth/SignatureService.php`)

**Technology**: Sodium Ed25519 signatures

**Features**:
- Verify signatures from TypeScript clients
- Match privacy-kit's signature format
- Public key validation

## 1.4 Authentication System

### Token Service (`src/Services/Token/TokenService.php`)

**Technology**: Firebase JWT

**Features**:
- Generate JWT tokens with expiration
- Persistent tokens (long-lived)
- Ephemeral tokens (5 minutes for OAuth)
- Token caching with file-based cache

**Token Types**:
1. **Persistent**: `HS256`, 365-day expiration
2. **Ephemeral**: `HS256`, 5-minute expiration

### Auth Flows to Implement

**1. Direct Signature Auth** (`POST /v1/auth/signature`)
- Client sends: `{ publicKey, signature, timestamp }`
- Server verifies signature
- Returns persistent JWT token

**2. CLI Auth Flow**
- Request: `POST /v1/auth/request` → creates `TerminalAuthRequest`
- Mobile approves: `POST /v1/auth/response`
- Poll: `GET /v1/auth/request/{id}` → returns token when approved

**3. Mobile Auth Flow**
- Request: `POST /v1/auth/account/request` → creates `AccountAuthRequest`
- QR code displayed
- Mobile scans and approves
- Poll: `GET /v1/auth/account/request/{id}`

### Auth Middleware (`src/App/Api/Middleware/AuthMiddleware.php`)

**Features**:
- JWT token extraction from `Authorization` header
- Token validation and caching
- User context injection into request
- Support for both persistent and ephemeral tokens

## 1.5 Routing Framework

### Web Framework: Slim 4

**Why Slim**:
- Lightweight (no bloat)
- PSR-7/PSR-15 compliant
- Excellent middleware support
- Easy to deploy on shared hosting

### Route Setup (`src/App/Api/Routes/`)

**Structure**:
```
src/App/Api/Routes/
├── auth.php          # Authentication endpoints
├── sessions.php      # Session CRUD
├── machines.php      # Machine management
├── artifacts.php     # Artifact handling
└── account.php       # Account settings
```

**Route Definition Pattern**:
```php
// auth.php
use Slim\App;
use Happy\App\Api\Middleware\AuthMiddleware;

return function (App $app) {
    $app->post('/v1/auth/signature', SignatureAuthController::class);

    $app->group('/v1', function ($group) {
        $group->get('/account', AccountController::class . ':getProfile');
    })->add(AuthMiddleware::class);
};
```

### Request Validation

**Technology**: Custom validation with array schemas (lighter than Zod)

**Pattern**:
```php
class Validator {
    public static function validate(array $data, array $rules): void {
        foreach ($rules as $field => $rule) {
            if ($rule['required'] && !isset($data[$field])) {
                throw new ValidationException("Missing field: $field");
            }
            // Type checking, format validation, etc.
        }
    }
}
```

## 1.6 Logging & Error Handling

### Logging Service (`src/Services/Logging/Logger.php`)

**Technology**: Monolog

**Features**:
- File-based logging to `storage/logs/`
- Timestamped log files (MM-DD-HH-MM-SS.log format)
- Structured logging with context
- Debug mode for development

### Error Handling

**Global Exception Handler**:
- Catch all exceptions
- Log to file
- Return JSON error responses
- Different handling for production vs. development

## 1.7 Configuration & Environment

### Environment Variables (`.env`)
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.example.com

DB_CONNECTION=sqlite
DB_DATABASE=storage/database/database.sqlite

MASTER_SECRET=your-encryption-secret
JWT_SECRET=your-jwt-secret

LOG_CHANNEL=file
LOG_LEVEL=info
```

## 1.8 Testing Infrastructure

### PHPUnit Setup
- Unit tests for encryption, auth, validation
- Integration tests for database operations
- Test database separate from production

### Test Structure
```
tests/
├── Unit/
│   ├── EncryptionServiceTest.php
│   ├── TokenServiceTest.php
│   └── SignatureServiceTest.php
└── Integration/
    ├── AuthFlowTest.php
    └── DatabaseTest.php
```

---

## Phase 1 Deliverables

### ✅ Completion Criteria
1. [ ] Composer project initializes successfully
2. [ ] SQLite database connects and migrates
3. [ ] Encryption service encrypts/decrypts data
4. [ ] Signature verification works with existing clients
5. [ ] JWT tokens generate and validate
6. [ ] Auth middleware protects routes
7. [ ] Basic routing framework responds to requests
8. [ ] Logging to file works
9. [ ] All unit tests pass

### 📦 Files Created (~20 files)
- Composer configuration
- Database connection & migrations (5 core models)
- Encryption service
- Authentication services (3 flows)
- Auth middleware
- Basic route definitions
- Logging setup
- Environment configuration
- Test scaffolding

### 🎯 What's Working
- Users can authenticate via signature
- Tokens are issued and validated
- Database is initialized with core tables
- Encrypted data can be stored/retrieved
- Basic API responds to requests

---

## Phase 1 Timeline Estimate

**Total**: 3-5 days of focused development

- Day 1: Project setup, Composer, database layer
- Day 2: Encryption & auth services
- Day 3: Authentication flows & middleware
- Day 4: Routing framework & validation
- Day 5: Testing & debugging

---

## Next: Phase 2

Phase 2 will implement:
- All database models with relationships
- Complete API endpoints for sessions, machines, artifacts
- Transaction support with retry logic
- Version control system
- KV store with atomic mutations
- Cursor-based pagination
