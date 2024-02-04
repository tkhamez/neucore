<?php

declare(strict_types=1);

use Neucore\Application;

// For the built-in PHP dev server, check for requests to be served as static files
if (PHP_SAPI == 'cli-server') {
    if (is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'])['path'])) {
        return false;
    }
}

require __DIR__ . '/../backend/vendor/autoload.php';

(new Application())->runWebApp();
