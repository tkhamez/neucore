<?php

declare(strict_types=1);

namespace Tests;

use Monolog\ErrorHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Neucore\Application;
use Neucore\Middleware\Psr15\RateLimitIP;

date_default_timezone_set('UTC');

require __DIR__ . '/../vendor/autoload.php';

// Setup error handler
$config = (new Application())->loadSettings(true);
error_reporting((int)$config['error_reporting']);
$handler = new StreamHandler($config['monolog']['path'], Level::Debug);
$log = new \Neucore\Log\Logger('Test');
$log->pushHandler($handler);
ErrorHandler::register($log);
ini_set('log_errors', '0');

// Other settings
RateLimitIP::$active = false;

// Create DB schema
/** @noinspection PhpUnhandledExceptionInspection */
(new Helper())->updateDbSchema();
