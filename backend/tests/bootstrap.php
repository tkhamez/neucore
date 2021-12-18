<?php

declare(strict_types=1);

namespace Tests;

use Neucore\Application;

date_default_timezone_set('UTC');

require __DIR__ . '/../vendor/autoload.php';

// Only used to get the PHP error reporting level from the .env file if it exists
$config = (new Application())->loadSettings(true);
error_reporting((int)$config['error_reporting']);

(new Helper())->updateDbSchema();
