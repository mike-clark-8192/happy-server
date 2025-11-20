<?php

/**
 * Route definitions for the API.
 */

use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use Happy\App\Api\Middleware\AuthMiddleware;
use Happy\App\Api\Middleware\CorsMiddleware;
use Happy\App\Api\Middleware\LoggingMiddleware;
use Happy\App\Api\Middleware\RateLimitMiddleware;
use Happy\App\Api\Controllers\SessionController;
use Happy\App\Api\Controllers\MachineController;
use Happy\App\Api\Controllers\ArtifactController;
use Happy\App\Api\Controllers\AuthController;
use Happy\App\Api\Controllers\AdminController;
use Happy\Services\Auth\TokenService;
use Happy\Services\Auth\SignatureService;

return function (App $app, array $config) {
    // Create services
    $tokenService = new TokenService($config['security']['jwt_secret']);
    $signatureService = new SignatureService();

    // Add global middleware (in reverse order - last added runs first)
    $app->add(new LoggingMiddleware());
    $app->add(new RateLimitMiddleware(
        $config['rate_limiting']['max_requests'] ?? 60,
        $config['rate_limiting']['window_seconds'] ?? 60,
        $config['paths']['cache_dir'] ?? null,
        $config['rate_limiting']['whitelist'] ?? ['127.0.0.1']
    ));
    $app->add(new CorsMiddleware(
        $config['cors']['allowed_origins'] ?? ['*'],
        $config['cors']['allowed_methods'] ?? ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        $config['cors']['allowed_headers'] ?? ['Authorization', 'Content-Type', 'X-Requested-With'],
        $config['cors']['allow_credentials'] ?? false
    ));

    // Health check
    $app->get('/health', function ($request, $response) {
        $response->getBody()->write(json_encode(['status' => 'ok']));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // Version endpoint
    $app->get('/v1/version', function ($request, $response) {
        $response->getBody()->write(json_encode([
            'version' => '2.0.0-php',
            'api' => 'v1',
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // Auth routes (no authentication required)
    $app->group('/v1/auth', function (RouteCollectorProxy $group) use ($tokenService, $signatureService) {
        $controller = new AuthController($tokenService, $signatureService);

        $group->post('/signature', [$controller, 'signature']);
        $group->post('/request', [$controller, 'createRequest']);
        $group->get('/request/{id}', [$controller, 'getRequest']);
        $group->post('/response', [$controller, 'respondToRequest']);
    });

    // Admin dashboard
    $adminController = new AdminController($config);
    $adminPath = $config['monitoring']['admin_dashboard_path'] ?? '/admin';
    $app->get($adminPath, [$adminController, 'dashboard']);
    $app->post($adminPath, [$adminController, 'login']);
    $app->get($adminPath . '/logout', [$adminController, 'logout']);

    // Protected routes (require authentication)
    $authMiddleware = new AuthMiddleware($tokenService);

    $app->group('/v1', function (RouteCollectorProxy $group) use ($config) {
        // Session routes
        $sessionController = new SessionController();
        $group->get('/sessions', [$sessionController, 'list']);
        $group->post('/sessions', [$sessionController, 'create']);
        $group->get('/sessions/{id}', [$sessionController, 'get']);
        $group->put('/sessions/{id}', [$sessionController, 'update']);
        $group->delete('/sessions/{id}', [$sessionController, 'delete']);
        $group->get('/sessions/{id}/messages', [$sessionController, 'getMessages']);
        $group->post('/sessions/{id}/messages', [$sessionController, 'createMessage']);

        // Machine routes
        $machineController = new MachineController();
        $group->get('/machines', [$machineController, 'list']);
        $group->post('/machines', [$machineController, 'register']);
        $group->get('/machines/{id}', [$machineController, 'get']);
        $group->put('/machines/{id}', [$machineController, 'update']);

        // Artifact routes
        $artifactController = new ArtifactController();
        $group->get('/artifacts/{id}', [$artifactController, 'get']);
        $group->post('/artifacts', [$artifactController, 'create']);
        $group->put('/artifacts/{id}', [$artifactController, 'update']);
        $group->delete('/artifacts/{id}', [$artifactController, 'delete']);
        $group->get('/sessions/{sessionId}/artifacts', [$artifactController, 'listBySession']);

    })->add($authMiddleware);
};
