<?php

/**
 * Application entry point.
 * Handles all HTTP requests through Slim framework.
 */

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Happy\Storage\Database;
use Happy\Storage\Migration;
use Happy\Storage\Model;
use Happy\Services\Encryption\EncryptionService;
use Happy\Services\Logging\Logger;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Load configuration
$config = require __DIR__ . '/../config/app.php';

// Initialize logging
Logger::init($config['logging']);

// Initialize database
Database::init($config['database']);

// Run migrations (in production, this should be a separate command)
if ($config['env'] !== 'production') {
    Migration::run();
}

// Initialize encryption service
$masterSecret = $config['security']['master_secret'];
if (empty($masterSecret)) {
    throw new \RuntimeException('MASTER_SECRET environment variable is required');
}
$encryption = new EncryptionService($masterSecret);
Model::setEncryptionService($encryption);

// Create Slim app
$app = AppFactory::create();

// Add error middleware
$app->addErrorMiddleware(
    $config['debug'],
    true,
    true
);

// Add body parsing middleware
$app->addBodyParsingMiddleware();

// Register routes
$routes = require __DIR__ . '/../src/App/Api/routes.php';
$routes($app, $config);

// Run application
$app->run();
