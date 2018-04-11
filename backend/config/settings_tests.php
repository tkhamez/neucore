<?php
/**
 *  Test settings, loaded additionally to settings_dev.php.
 */

return [
    'config' => [
        'monolog' => [
            'path' => 'php://stderr',
        ],
        'doctrine' => [
            'connection' => [
                'url' => getenv('BRAVECORE_TEST_DATABASE_URL'),
            ],
        ],
    ],
];
