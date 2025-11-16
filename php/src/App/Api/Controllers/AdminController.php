<?php

namespace Happy\App\Api\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Happy\Storage\Database;
use Happy\Services\Logging\Logger;

/**
 * Admin dashboard controller for system status and monitoring.
 * Password protected for security.
 */
class AdminController
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Display the admin dashboard with system status.
     */
    public function dashboard(Request $request, Response $response): Response
    {
        // Check authentication
        if (!$this->isAuthenticated($request)) {
            return $this->renderLoginPage($response);
        }

        // Gather system metrics
        $metrics = $this->getSystemMetrics();

        // Render dashboard
        $html = $this->renderDashboard($metrics);

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html');
    }

    /**
     * Handle login form submission.
     */
    public function login(Request $request, Response $response): Response
    {
        $params = $request->getParsedBody();
        $password = $params['password'] ?? '';

        $adminPassword = $this->config['security']['admin_password'] ?? null;

        if ($password === $adminPassword && !empty($adminPassword)) {
            // Set session cookie
            setcookie('admin_auth', hash('sha256', $adminPassword . session_id()), [
                'expires' => time() + 3600,
                'path' => '/',
                'httponly' => true,
                'secure' => true,
                'samesite' => 'Strict',
            ]);

            return $response
                ->withHeader('Location', $this->config['monitoring']['admin_dashboard_path'] ?? '/admin')
                ->withStatus(302);
        }

        Logger::warning('Failed admin login attempt', [
            'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown'
        ]);

        return $this->renderLoginPage($response, 'Invalid password');
    }

    /**
     * Handle logout.
     */
    public function logout(Request $request, Response $response): Response
    {
        setcookie('admin_auth', '', time() - 3600, '/');

        return $response
            ->withHeader('Location', $this->config['monitoring']['admin_dashboard_path'] ?? '/admin')
            ->withStatus(302);
    }

    /**
     * Check if user is authenticated.
     */
    private function isAuthenticated(Request $request): bool
    {
        $cookies = $request->getCookieParams();
        $authCookie = $cookies['admin_auth'] ?? '';

        $adminPassword = $this->config['security']['admin_password'] ?? null;

        if (empty($adminPassword)) {
            return false;
        }

        $expectedHash = hash('sha256', $adminPassword . session_id());

        return $authCookie === $expectedHash;
    }

    /**
     * Get system metrics and status.
     */
    private function getSystemMetrics(): array
    {
        $db = Database::connection();

        return [
            'system' => [
                'php_version' => PHP_VERSION,
                'memory_usage' => $this->formatBytes(memory_get_usage(true)),
                'peak_memory' => $this->formatBytes(memory_get_peak_usage(true)),
                'uptime' => $this->getUptime(),
            ],
            'database' => [
                'type' => $this->config['database']['connection'] ?? 'sqlite',
                'size' => $this->getDatabaseSize(),
                'accounts' => $this->getTableCount('accounts'),
                'sessions' => $this->getTableCount('sessions'),
                'machines' => $this->getTableCount('machines'),
                'artifacts' => $this->getTableCount('artifacts'),
            ],
            'activity' => [
                'active_users_24h' => $this->getActiveUsers(24),
                'active_sessions_1h' => $this->getActiveSessions(1),
                'online_machines' => $this->getOnlineMachines(),
                'total_messages' => $this->getTableCount('session_messages'),
            ],
            'performance' => [
                'avg_response_time' => $this->getAverageResponseTime(),
                'error_rate_24h' => $this->getErrorRate(),
                'cache_hit_rate' => $this->getCacheHitRate(),
            ],
            'logs' => [
                'recent_errors' => $this->getRecentErrors(),
                'log_size' => $this->getLogSize(),
            ],
        ];
    }

    private function getTableCount(string $table): int
    {
        try {
            $result = Database::connection()
                ->table($table)
                ->count();
            return $result;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getActiveUsers(int $hours): int
    {
        try {
            return Database::connection()
                ->table('accounts')
                ->where('updated_at', '>', date('Y-m-d H:i:s', strtotime("-{$hours} hours")))
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getActiveSessions(int $hours): int
    {
        try {
            return Database::connection()
                ->table('sessions')
                ->where('updated_at', '>', date('Y-m-d H:i:s', strtotime("-{$hours} hours")))
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getOnlineMachines(): int
    {
        try {
            return Database::connection()
                ->table('machines')
                ->where('last_alive_at', '>', date('Y-m-d H:i:s', strtotime('-5 minutes')))
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getDatabaseSize(): string
    {
        $dbPath = $this->config['database']['database'] ?? '';

        if (file_exists($dbPath)) {
            return $this->formatBytes(filesize($dbPath));
        }

        return 'N/A';
    }

    private function getUptime(): string
    {
        // This would need a persistent file to track actual uptime
        // For now, return process uptime
        return 'N/A';
    }

    private function getAverageResponseTime(): string
    {
        return 'N/A'; // Would need metrics collection
    }

    private function getErrorRate(): string
    {
        return 'N/A'; // Would need metrics collection
    }

    private function getCacheHitRate(): string
    {
        return 'N/A'; // Would need metrics collection
    }

    private function getRecentErrors(): array
    {
        $logPath = $this->config['logging']['file_pattern'] ?? '';
        $logPath = str_replace('{date}', date('Y-m-d'), $logPath);

        if (!file_exists($logPath)) {
            return [];
        }

        $content = file_get_contents($logPath);
        $lines = explode("\n", $content);
        $errors = [];

        foreach (array_reverse($lines) as $line) {
            if (stripos($line, 'ERROR') !== false || stripos($line, 'CRITICAL') !== false) {
                $errors[] = $line;
                if (count($errors) >= 10) break;
            }
        }

        return $errors;
    }

    private function getLogSize(): string
    {
        $logDir = dirname($this->config['logging']['file_pattern'] ?? '');
        $size = 0;

        if (is_dir($logDir)) {
            foreach (glob($logDir . '/*.log') as $file) {
                $size += filesize($file);
            }
        }

        return $this->formatBytes($size);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    private function renderLoginPage(Response $response, string $error = ''): Response
    {
        $errorHtml = $error ? "<div class='error'>{$error}</div>" : '';

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Admin Login - Happy Server</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f5f5f5; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .login-box { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 300px; }
        h1 { margin: 0 0 20px; font-size: 24px; text-align: center; }
        input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #007bff; color: white; border: none; border-radius: 4px; font-size: 14px; cursor: pointer; margin-top: 10px; }
        button:hover { background: #0056b3; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="login-box">
        <h1>🔒 Admin Login</h1>
        {$errorHtml}
        <form method="POST">
            <input type="password" name="password" placeholder="Admin Password" required autofocus>
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>
HTML;

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html');
    }

    private function renderDashboard(array $metrics): string
    {
        $logoutUrl = ($this->config['monitoring']['admin_dashboard_path'] ?? '/admin') . '/logout';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard - Happy Server</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f7; color: #1d1d1f; }
        .header { background: white; padding: 20px 40px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 28px; font-weight: 600; }
        .logout { background: #ff3b30; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 14px; }
        .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .card { background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .card h2 { font-size: 18px; margin-bottom: 16px; color: #007aff; font-weight: 600; }
        .metric { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
        .metric:last-child { border-bottom: none; }
        .metric-label { color: #86868b; font-size: 14px; }
        .metric-value { font-weight: 600; font-size: 16px; }
        .status-ok { color: #34c759; }
        .status-warning { color: #ff9500; }
        .status-error { color: #ff3b30; }
        .logs { background: #1d1d1f; color: #f5f5f7; padding: 16px; border-radius: 8px; font-family: 'Monaco', 'Courier New', monospace; font-size: 12px; overflow-x: auto; max-height: 400px; overflow-y: auto; }
        .logs pre { margin: 0; white-space: pre-wrap; }
        .refresh-info { text-align: center; color: #86868b; font-size: 14px; margin-top: 30px; }
    </style>
    <script>
        // Auto-refresh every 30 seconds
        setTimeout(() => window.location.reload(), 30000);
    </script>
</head>
<body>
    <div class="header">
        <h1>📊 Happy Server Dashboard</h1>
        <a href="{$logoutUrl}" class="logout">Logout</a>
    </div>

    <div class="container">
        <div class="grid">
            <div class="card">
                <h2>System Info</h2>
                <div class="metric">
                    <span class="metric-label">PHP Version</span>
                    <span class="metric-value">{$metrics['system']['php_version']}</span>
                </div>
                <div class="metric">
                    <span class="metric-label">Memory Usage</span>
                    <span class="metric-value">{$metrics['system']['memory_usage']}</span>
                </div>
                <div class="metric">
                    <span class="metric-label">Peak Memory</span>
                    <span class="metric-value">{$metrics['system']['peak_memory']}</span>
                </div>
            </div>

            <div class="card">
                <h2>Database</h2>
                <div class="metric">
                    <span class="metric-label">Type</span>
                    <span class="metric-value">{$metrics['database']['type']}</span>
                </div>
                <div class="metric">
                    <span class="metric-label">Size</span>
                    <span class="metric-value">{$metrics['database']['size']}</span>
                </div>
                <div class="metric">
                    <span class="metric-label">Accounts</span>
                    <span class="metric-value">{$metrics['database']['accounts']}</span>
                </div>
                <div class="metric">
                    <span class="metric-label">Sessions</span>
                    <span class="metric-value">{$metrics['database']['sessions']}</span>
                </div>
                <div class="metric">
                    <span class="metric-label">Machines</span>
                    <span class="metric-value">{$metrics['database']['machines']}</span>
                </div>
            </div>

            <div class="card">
                <h2>Activity</h2>
                <div class="metric">
                    <span class="metric-label">Active Users (24h)</span>
                    <span class="metric-value status-ok">{$metrics['activity']['active_users_24h']}</span>
                </div>
                <div class="metric">
                    <span class="metric-label">Active Sessions (1h)</span>
                    <span class="metric-value status-ok">{$metrics['activity']['active_sessions_1h']}</span>
                </div>
                <div class="metric">
                    <span class="metric-label">Online Machines</span>
                    <span class="metric-value status-ok">{$metrics['activity']['online_machines']}</span>
                </div>
                <div class="metric">
                    <span class="metric-label">Total Messages</span>
                    <span class="metric-value">{$metrics['activity']['total_messages']}</span>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>Recent Errors</h2>
            <div class="logs">
                <pre><?php echo empty($metrics['logs']['recent_errors']) ? 'No recent errors' : implode("\n", array_map('htmlspecialchars', $metrics['logs']['recent_errors'])); ?></pre>
            </div>
            <div class="metric" style="margin-top: 16px;">
                <span class="metric-label">Total Log Size</span>
                <span class="metric-value">{$metrics['logs']['log_size']}</span>
            </div>
        </div>

        <div class="refresh-info">
            Auto-refreshing every 30 seconds • Last updated: <?php echo date('Y-m-d H:i:s'); ?>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
