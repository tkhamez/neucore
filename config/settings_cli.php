<?php
use Brave\Core\Application;

return [
    'config' => [
        'monolog' => [
            'path' => Application::ROOT_DIR . '/var/logs/app-cli.log',
        ],
    ],
];
