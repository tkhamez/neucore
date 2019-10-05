<?php declare(strict_types=1);

namespace Tests;

require __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('UTC');

(new Helper())->updateDbSchema();
