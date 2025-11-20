<?php

namespace Happy\Storage;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;

/**
 * Database connection manager using Illuminate/Database (Eloquent).
 * Configures SQLite connection with WAL mode for better concurrency.
 */
class Database
{
    private static ?Capsule $capsule = null;
    private static bool $initialized = false;

    /**
     * Initialize the database connection.
     *
     * @param array $config Database configuration
     */
    public static function init(array $config): void
    {
        if (self::$initialized) {
            return;
        }

        self::$capsule = new Capsule();

        $connection = $config['connection'] ?? 'sqlite';

        if ($connection === 'sqlite') {
            self::$capsule->addConnection([
                'driver' => 'sqlite',
                'database' => $config['database'] ?? ':memory:',
                'prefix' => $config['prefix'] ?? '',
            ]);
        } else {
            // MySQL/PostgreSQL support for future use
            self::$capsule->addConnection([
                'driver' => $connection,
                'host' => $config['host'] ?? 'localhost',
                'port' => $config['port'] ?? 3306,
                'database' => $config['database'],
                'username' => $config['username'] ?? '',
                'password' => $config['password'] ?? '',
                'charset' => $config['charset'] ?? 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => $config['prefix'] ?? '',
            ]);
        }

        // Set event dispatcher for model events
        self::$capsule->setEventDispatcher(new Dispatcher(new Container()));

        // Make this Capsule instance available globally
        self::$capsule->setAsGlobal();

        // Boot Eloquent ORM
        self::$capsule->bootEloquent();

        // Enable WAL mode for SQLite
        if ($connection === 'sqlite') {
            self::$capsule->getConnection()->statement('PRAGMA journal_mode=WAL');
            self::$capsule->getConnection()->statement('PRAGMA synchronous=NORMAL');
            self::$capsule->getConnection()->statement('PRAGMA foreign_keys=ON');
        }

        self::$initialized = true;
    }

    /**
     * Get the database connection.
     *
     * @return \Illuminate\Database\Connection
     */
    public static function connection(): \Illuminate\Database\Connection
    {
        if (!self::$initialized) {
            throw new \RuntimeException('Database not initialized. Call Database::init() first.');
        }

        return self::$capsule->getConnection();
    }

    /**
     * Get the Capsule manager.
     *
     * @return Capsule
     */
    public static function capsule(): Capsule
    {
        if (!self::$initialized) {
            throw new \RuntimeException('Database not initialized. Call Database::init() first.');
        }

        return self::$capsule;
    }

    /**
     * Get the schema builder.
     *
     * @return \Illuminate\Database\Schema\Builder
     */
    public static function schema(): \Illuminate\Database\Schema\Builder
    {
        return self::connection()->getSchemaBuilder();
    }

    /**
     * Run a callback within a transaction with automatic retry on conflicts.
     *
     * @param callable $callback
     * @param int $maxRetries
     * @return mixed
     */
    public static function transaction(callable $callback, int $maxRetries = 3)
    {
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                return self::connection()->transaction($callback);
            } catch (\Exception $e) {
                // Retry on SQLite busy/locked errors
                if (self::isRetryableError($e) && $attempt < $maxRetries - 1) {
                    $attempt++;
                    usleep(100000 * $attempt); // Exponential backoff
                    continue;
                }

                throw $e;
            }
        }
    }

    /**
     * Check if an exception is retryable (SQLite locked/busy).
     *
     * @param \Exception $e
     * @return bool
     */
    private static function isRetryableError(\Exception $e): bool
    {
        $message = $e->getMessage();
        return str_contains($message, 'database is locked') ||
               str_contains($message, 'SQLITE_BUSY');
    }

    /**
     * Reset the database connection (for testing).
     */
    public static function reset(): void
    {
        self::$capsule = null;
        self::$initialized = false;
    }
}
