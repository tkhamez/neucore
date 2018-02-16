<?php
use Brave\Core\Application;

// For the built-in PHP dev server, check for request that should be served as a static file
if (PHP_SAPI == 'cli-server') {
    $url  = parse_url($_SERVER['REQUEST_URI']);
    $file = __DIR__ . $url['path'];
    if (is_file($file)) {
        return false;
    }
    session_save_path(sys_get_temp_dir()); // the normal path might not be writable for this user
}

require __DIR__ . '/../backend/vendor/autoload.php';

(new Application())->run();
