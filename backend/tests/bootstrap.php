<?php

declare(strict_types=1);

namespace Tests;

error_reporting(E_ALL);
date_default_timezone_set('UTC');

require __DIR__ . '/../vendor/autoload.php';

(new Helper())->updateDbSchema();
