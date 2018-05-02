<?php declare(strict_types=1);

/**
 *  Test settings, loaded additionally to other settings files.
 */

return [
    'config' => [
        'monolog' => [
            'path' => 'php://stderr',
        ],
        'doctrine' => [
            'connection' => [
                'url' => getenv('BRAVECORE_TEST_DATABASE_URL')
            ],
        ],
    ],
];
