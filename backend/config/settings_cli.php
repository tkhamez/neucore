<?php declare(strict_types=1);

use Brave\Core\Application;

return [
    'config' => [
        'monolog' => [
            'path' => Application::ROOT_DIR . '/var/logs/app-cli-'.date('Y-m').'.log',
        ],
    ],
];
