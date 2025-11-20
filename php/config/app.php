<?php

/**
 * Central configuration file for Happy Server (PHP Edition)
 *
 * All important application settings are centralized here.
 * Values are loaded from environment variables with sensible defaults.
 */

return [
    /**
     * Application Environment
     * Options: production, development, testing
     */
    'env' => $_ENV['APP_ENV'] ?? 'production',

    /**
     * Debug Mode
     * Enable detailed error messages (only for development)
     */
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),

    /**
     * Application URL
     * Base URL for the application
     */
    'url' => $_ENV['APP_URL'] ?? 'http://localhost',

    /**
     * Server Configuration
     */
    'server' => [
        'http_port' => (int)($_ENV['HTTP_PORT'] ?? 3000),
        'websocket_port' => (int)($_ENV['WEBSOCKET_PORT'] ?? 8080),
        'host' => $_ENV['SERVER_HOST'] ?? '0.0.0.0',
    ],

    /**
     * Database Configuration
     */
    'database' => [
        'connection' => $_ENV['DB_CONNECTION'] ?? 'sqlite',
        'database' => $_ENV['DB_DATABASE'] ?? __DIR__ . '/../storage/database/database.sqlite',
        'prefix' => $_ENV['DB_PREFIX'] ?? '',

        // PostgreSQL/MySQL options (if needed)
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'port' => (int)($_ENV['DB_PORT'] ?? 5432),
        'username' => $_ENV['DB_USERNAME'] ?? '',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
        'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
    ],

    /**
     * Security Configuration
     */
    'security' => [
        /**
         * Master secret for encryption (REQUIRED)
         * Used for deriving encryption keys for sensitive data
         */
        'master_secret' => $_ENV['MASTER_SECRET'] ?? null,

        /**
         * JWT secret for token signing (REQUIRED)
         */
        'jwt_secret' => $_ENV['JWT_SECRET'] ?? null,

        /**
         * Admin dashboard password (REQUIRED for production)
         * Used to protect the status/overview admin page
         */
        'admin_password' => $_ENV['ADMIN_PASSWORD'] ?? null,

        /**
         * Token expiration times (in seconds)
         */
        'token_expiry' => [
            'persistent' => 365 * 24 * 60 * 60, // 1 year
            'ephemeral' => 5 * 60, // 5 minutes
        ],
    ],

    /**
     * Logging Configuration
     */
    'logging' => [
        /**
         * Log level: debug, info, warning, error
         * Default: info
         */
        'level' => $_ENV['LOG_LEVEL'] ?? 'info',

        /**
         * Log channel: file, stdout, null
         * - file: Write to log files in storage/logs/
         * - stdout: Write to standard output
         * - null: Disable logging
         */
        'channel' => $_ENV['LOG_CHANNEL'] ?? 'file',

        /**
         * Log file path pattern
         * Supports: {date}, {level}, {timestamp}
         */
        'file_pattern' => $_ENV['LOG_FILE_PATTERN'] ?? 'storage/logs/{date}.log',

        /**
         * Log file rotation
         */
        'max_files' => (int)($_ENV['LOG_MAX_FILES'] ?? 30),

        /**
         * Include request/response logging
         */
        'log_requests' => filter_var($_ENV['LOG_REQUESTS'] ?? true, FILTER_VALIDATE_BOOLEAN),

        /**
         * Include SQL query logging (debug only)
         */
        'log_queries' => filter_var($_ENV['LOG_QUERIES'] ?? false, FILTER_VALIDATE_BOOLEAN),
    ],

    /**
     * Cache Configuration
     */
    'cache' => [
        /**
         * Cache driver: file, redis, null
         */
        'driver' => $_ENV['CACHE_DRIVER'] ?? 'file',

        /**
         * Cache TTL (in seconds)
         */
        'ttl' => (int)($_ENV['CACHE_TTL'] ?? 3600),

        /**
         * Redis configuration (if using redis driver)
         */
        'redis' => [
            'host' => $_ENV['REDIS_HOST'] ?? 'localhost',
            'port' => (int)($_ENV['REDIS_PORT'] ?? 6379),
            'password' => $_ENV['REDIS_PASSWORD'] ?? null,
            'database' => (int)($_ENV['REDIS_DATABASE'] ?? 0),
        ],
    ],

    /**
     * Session Configuration
     */
    'session' => [
        /**
         * Session timeout (in seconds)
         */
        'timeout' => (int)($_ENV['SESSION_TIMEOUT'] ?? 3600),

        /**
         * Clean up old sessions automatically
         */
        'auto_cleanup' => filter_var($_ENV['SESSION_AUTO_CLEANUP'] ?? true, FILTER_VALIDATE_BOOLEAN),
    ],

    /**
     * OAuth Configuration
     */
    'oauth' => [
        'github' => [
            'client_id' => $_ENV['GITHUB_CLIENT_ID'] ?? null,
            'client_secret' => $_ENV['GITHUB_CLIENT_SECRET'] ?? null,
            'redirect_uri' => $_ENV['GITHUB_REDIRECT_URI'] ?? null,
        ],
    ],

    /**
     * Push Notifications Configuration
     */
    'push' => [
        'fcm' => [
            'server_key' => $_ENV['FCM_SERVER_KEY'] ?? null,
        ],
    ],

    /**
     * File Storage Configuration
     */
    'storage' => [
        /**
         * Storage driver: local, s3
         */
        'driver' => $_ENV['STORAGE_DRIVER'] ?? 'local',

        /**
         * Local storage path
         */
        'local_path' => $_ENV['STORAGE_PATH'] ?? __DIR__ . '/../storage/files',

        /**
         * Maximum upload size (in bytes)
         */
        'max_upload_size' => (int)($_ENV['MAX_UPLOAD_SIZE'] ?? 10 * 1024 * 1024), // 10MB

        /**
         * Allowed file extensions
         */
        'allowed_extensions' => explode(',', $_ENV['ALLOWED_EXTENSIONS'] ?? 'jpg,jpeg,png,gif,pdf,txt,md'),

        /**
         * S3 configuration (if using s3 driver)
         */
        's3' => [
            'key' => $_ENV['AWS_ACCESS_KEY_ID'] ?? null,
            'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'] ?? null,
            'region' => $_ENV['AWS_DEFAULT_REGION'] ?? 'us-east-1',
            'bucket' => $_ENV['AWS_BUCKET'] ?? null,
        ],
    ],

    /**
     * Rate Limiting Configuration
     */
    'rate_limiting' => [
        /**
         * Enable rate limiting
         */
        'enabled' => filter_var($_ENV['RATE_LIMIT_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),

        /**
         * Maximum requests per window
         */
        'max_requests' => (int)($_ENV['RATE_LIMIT_MAX_REQUESTS'] ?? 60),

        /**
         * Time window in seconds
         */
        'window_seconds' => (int)($_ENV['RATE_LIMIT_WINDOW'] ?? 60),

        /**
         * Whitelist IPs (comma-separated)
         */
        'whitelist' => explode(',', $_ENV['RATE_LIMIT_WHITELIST'] ?? '127.0.0.1'),
    ],

    /**
     * CORS Configuration
     */
    'cors' => [
        /**
         * Allowed origins (* for all)
         */
        'allowed_origins' => explode(',', $_ENV['CORS_ALLOWED_ORIGINS'] ?? '*'),

        /**
         * Allowed HTTP methods
         */
        'allowed_methods' => explode(',', $_ENV['CORS_ALLOWED_METHODS'] ?? 'GET,POST,PUT,DELETE,OPTIONS'),

        /**
         * Allowed headers
         */
        'allowed_headers' => explode(',', $_ENV['CORS_ALLOWED_HEADERS'] ?? 'Authorization,Content-Type,X-Requested-With'),

        /**
         * Allow credentials
         */
        'allow_credentials' => filter_var($_ENV['CORS_ALLOW_CREDENTIALS'] ?? false, FILTER_VALIDATE_BOOLEAN),
    ],

    /**
     * Paths Configuration
     */
    'paths' => [
        'cache_dir' => $_ENV['CACHE_DIR'] ?? __DIR__ . '/../storage/cache',
        'logs_dir' => $_ENV['LOGS_DIR'] ?? __DIR__ . '/../storage/logs',
    ],

    /**
     * Monitoring & Metrics Configuration
     */
    'monitoring' => [
        /**
         * Enable Prometheus metrics endpoint
         */
        'metrics_enabled' => filter_var($_ENV['METRICS_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),

        /**
         * Metrics endpoint path
         */
        'metrics_path' => $_ENV['METRICS_PATH'] ?? '/metrics',

        /**
         * Enable status/overview admin page
         */
        'admin_dashboard_enabled' => filter_var($_ENV['ADMIN_DASHBOARD_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),

        /**
         * Admin dashboard path
         */
        'admin_dashboard_path' => $_ENV['ADMIN_DASHBOARD_PATH'] ?? '/admin',

        /**
         * Track performance metrics
         */
        'track_performance' => filter_var($_ENV['TRACK_PERFORMANCE'] ?? true, FILTER_VALIDATE_BOOLEAN),
    ],

    /**
     * WebSocket Configuration
     */
    'websocket' => [
        /**
         * Enable WebSocket server
         */
        'enabled' => filter_var($_ENV['WEBSOCKET_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),

        /**
         * Heartbeat interval (in seconds)
         */
        'heartbeat_interval' => (int)($_ENV['WEBSOCKET_HEARTBEAT'] ?? 30),

        /**
         * Maximum connections per user
         */
        'max_connections_per_user' => (int)($_ENV['WEBSOCKET_MAX_CONNECTIONS'] ?? 10),
    ],

    /**
     * Compatibility Configuration
     */
    'compatibility' => [
        /**
         * Node.js server compatibility mode
         * Ensures 100% API compatibility with TypeScript server
         */
        'node_compatible' => filter_var($_ENV['NODE_COMPATIBLE'] ?? true, FILTER_VALIDATE_BOOLEAN),

        /**
         * Strict validation (enforce Zod-like validation)
         */
        'strict_validation' => filter_var($_ENV['STRICT_VALIDATION'] ?? true, FILTER_VALIDATE_BOOLEAN),

        /**
         * Legacy field names (camelCase vs snake_case)
         */
        'use_camel_case' => filter_var($_ENV['USE_CAMEL_CASE'] ?? true, FILTER_VALIDATE_BOOLEAN),
    ],
];
