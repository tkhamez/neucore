<?php declare(strict_types=1);

use Brave\Core\Application;

// For the built-in PHP dev server, check for request that should be served as a static file
if (PHP_SAPI == 'cli-server') {
    $url = parse_url($_SERVER['REQUEST_URI']);
    $file = __DIR__ . $url['path'];
    if (is_file($file)) {
        return false;
    }
}

require __DIR__ . '/../backend/vendor/autoload.php';

// Run the web application.
(new Application())->getApp()->run();
