<?php declare(strict_types=1);

use Neucore\Application;

return [
    'config' => [
        'monolog' => [
            'path' => Application::ROOT_DIR . '/var/logs/app-cli-'.date('o\wW').'.log',
        ],
    ],
];
