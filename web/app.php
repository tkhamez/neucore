<?php

declare(strict_types=1);

use Neucore\Application;

require __DIR__ . '/../backend/vendor/autoload.php';

(new Application())->runWebApp();
