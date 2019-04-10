<?php declare(strict_types=1);

return [
    'config' => [
        'monolog' => [
            'path' => 'php://stderr',
        ],
        'guzzle' => [
            'cache' => [
                'dir' => '/tmp/http'
            ],
        ],
        'di' => [
            'cache_dir' => '/tmp/di'
        ]
    ],
];
