<?php

namespace Happy\Services\Logging;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

/**
 * Centralized logging service using Monolog.
 * Supports file, stdout, and null channels with configurable log levels.
 */
class Logger
{
    private static ?MonologLogger $instance = null;
    private static array $config = [];

    /**
     * Initialize the logger with configuration.
     *
     * @param array $config Logging configuration
     */
    public static function init(array $config): void
    {
        self::$config = $config;
        self::$instance = null; // Reset instance to apply new config
    }

    /**
     * Get or create the logger instance.
     *
     * @return MonologLogger
     */
    private static function getInstance(): MonologLogger
    {
        if (self::$instance === null) {
            self::$instance = self::createLogger();
        }

        return self::$instance;
    }

    /**
     * Create a new logger instance based on configuration.
     *
     * @return MonologLogger
     */
    private static function createLogger(): MonologLogger
    {
        $channel = self::$config['channel'] ?? 'file';
        $level = self::getLogLevel(self::$config['level'] ?? 'info');

        $logger = new MonologLogger('happy-server');

        // Set up formatter
        $formatter = new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
            'Y-m-d H:i:s',
            true,
            true
        );

        // Add appropriate handler based on channel
        switch ($channel) {
            case 'file':
                $handler = self::createFileHandler($level);
                break;

            case 'stdout':
                $handler = new StreamHandler('php://stdout', $level);
                break;

            case 'null':
                // No-op handler - logging disabled
                return $logger;

            default:
                $handler = self::createFileHandler($level);
        }

        $handler->setFormatter($formatter);
        $logger->pushHandler($handler);

        return $logger;
    }

    /**
     * Create a rotating file handler.
     *
     * @param int $level Log level
     * @return RotatingFileHandler
     */
    private static function createFileHandler(int $level): RotatingFileHandler
    {
        $pattern = self::$config['file_pattern'] ?? 'storage/logs/{date}.log';
        $logPath = str_replace('{date}', date('Y-m-d'), $pattern);
        $maxFiles = self::$config['max_files'] ?? 30;

        // Ensure log directory exists
        $logDir = dirname($logPath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        return new RotatingFileHandler($logPath, $maxFiles, $level);
    }

    /**
     * Convert string log level to Monolog constant.
     *
     * @param string $level
     * @return int
     */
    private static function getLogLevel(string $level): int
    {
        return match(strtolower($level)) {
            'debug' => MonologLogger::DEBUG,
            'info' => MonologLogger::INFO,
            'warning', 'warn' => MonologLogger::WARNING,
            'error' => MonologLogger::ERROR,
            'critical' => MonologLogger::CRITICAL,
            default => MonologLogger::INFO,
        };
    }

    /**
     * Log a debug message.
     *
     * @param string $message
     * @param array $context
     */
    public static function debug(string $message, array $context = []): void
    {
        self::getInstance()->debug($message, $context);
    }

    /**
     * Log an info message.
     *
     * @param string $message
     * @param array $context
     */
    public static function info(string $message, array $context = []): void
    {
        self::getInstance()->info($message, $context);
    }

    /**
     * Log a warning message.
     *
     * @param string $message
     * @param array $context
     */
    public static function warning(string $message, array $context = []): void
    {
        self::getInstance()->warning($message, $context);
    }

    /**
     * Log an error message.
     *
     * @param string $message
     * @param array $context
     */
    public static function error(string $message, array $context = []): void
    {
        self::getInstance()->error($message, $context);
    }

    /**
     * Log a critical message.
     *
     * @param string $message
     * @param array $context
     */
    public static function critical(string $message, array $context = []): void
    {
        self::getInstance()->critical($message, $context);
    }

    /**
     * Log an HTTP request.
     *
     * @param string $method
     * @param string $path
     * @param array $context
     */
    public static function logRequest(string $method, string $path, array $context = []): void
    {
        if (self::$config['log_requests'] ?? true) {
            self::info("HTTP Request: $method $path", $context);
        }
    }

    /**
     * Log an HTTP response.
     *
     * @param int $statusCode
     * @param float $duration Duration in milliseconds
     * @param array $context
     */
    public static function logResponse(int $statusCode, float $duration, array $context = []): void
    {
        if (self::$config['log_requests'] ?? true) {
            $context['status'] = $statusCode;
            $context['duration_ms'] = round($duration, 2);
            self::info("HTTP Response", $context);
        }
    }

    /**
     * Log a database query (debug mode only).
     *
     * @param string $query
     * @param array $bindings
     */
    public static function logQuery(string $query, array $bindings = []): void
    {
        if (self::$config['log_queries'] ?? false) {
            self::debug("SQL Query: $query", ['bindings' => $bindings]);
        }
    }
}
