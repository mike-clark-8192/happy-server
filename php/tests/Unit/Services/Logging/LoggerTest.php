<?php

namespace Tests\Unit\Services\Logging;

use PHPUnit\Framework\TestCase;
use Happy\Services\Logging\Logger;

class LoggerTest extends TestCase
{
    private string $testLogPath;

    protected function setUp(): void
    {
        // Use a temporary log path for testing
        $this->testLogPath = sys_get_temp_dir() . '/happy-test-' . uniqid() . '.log';

        Logger::init([
            'channel' => 'file',
            'level' => 'debug',
            'file_pattern' => $this->testLogPath,
            'max_files' => 1,
            'log_requests' => true,
            'log_queries' => true,
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up test log file
        if (file_exists($this->testLogPath)) {
            unlink($this->testLogPath);
        }
    }

    public function testLogDebugMessage(): void
    {
        Logger::debug('Debug test message', ['key' => 'value']);

        $this->assertFileExists($this->testLogPath);
        $content = file_get_contents($this->testLogPath);
        $this->assertStringContainsString('DEBUG', $content);
        $this->assertStringContainsString('Debug test message', $content);
    }

    public function testLogInfoMessage(): void
    {
        Logger::info('Info test message');

        $content = file_get_contents($this->testLogPath);
        $this->assertStringContainsString('INFO', $content);
        $this->assertStringContainsString('Info test message', $content);
    }

    public function testLogWarningMessage(): void
    {
        Logger::warning('Warning test message');

        $content = file_get_contents($this->testLogPath);
        $this->assertStringContainsString('WARNING', $content);
        $this->assertStringContainsString('Warning test message', $content);
    }

    public function testLogErrorMessage(): void
    {
        Logger::error('Error test message');

        $content = file_get_contents($this->testLogPath);
        $this->assertStringContainsString('ERROR', $content);
        $this->assertStringContainsString('Error test message', $content);
    }

    public function testLogCriticalMessage(): void
    {
        Logger::critical('Critical test message');

        $content = file_get_contents($this->testLogPath);
        $this->assertStringContainsString('CRITICAL', $content);
        $this->assertStringContainsString('Critical test message', $content);
    }

    public function testLogWithContext(): void
    {
        Logger::info('Message with context', [
            'userId' => '12345',
            'action' => 'login'
        ]);

        $content = file_get_contents($this->testLogPath);
        $this->assertStringContainsString('Message with context', $content);
        $this->assertStringContainsString('userId', $content);
        $this->assertStringContainsString('12345', $content);
    }

    public function testLogRequest(): void
    {
        Logger::logRequest('GET', '/api/sessions', ['userId' => '123']);

        $content = file_get_contents($this->testLogPath);
        $this->assertStringContainsString('HTTP Request', $content);
        $this->assertStringContainsString('GET /api/sessions', $content);
    }

    public function testLogResponse(): void
    {
        Logger::logResponse(200, 45.67, ['path' => '/api/sessions']);

        $content = file_get_contents($this->testLogPath);
        $this->assertStringContainsString('HTTP Response', $content);
        $this->assertStringContainsString('200', $content);
        $this->assertStringContainsString('45.67', $content);
    }

    public function testLogQuery(): void
    {
        Logger::logQuery('SELECT * FROM users WHERE id = ?', [123]);

        $content = file_get_contents($this->testLogPath);
        $this->assertStringContainsString('SQL Query', $content);
        $this->assertStringContainsString('SELECT * FROM users', $content);
    }

    public function testNullChannelDisablesLogging(): void
    {
        $nullLogPath = sys_get_temp_dir() . '/happy-null-' . uniqid() . '.log';

        Logger::init([
            'channel' => 'null',
            'file_pattern' => $nullLogPath,
        ]);

        Logger::info('This should not be logged');

        $this->assertFileDoesNotExist($nullLogPath);
    }
}
