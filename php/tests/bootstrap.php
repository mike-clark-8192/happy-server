<?php

require __DIR__ . '/../vendor/autoload.php';

// Load test environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../', '.env.testing');
$dotenv->safeLoad();

// Set timezone
date_default_timezone_set('UTC');
