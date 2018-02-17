<?php
return [
    'config' => [
        'doctrine' => [
            'connection' => [
                'url' => getenv('BRAVECORE_TEST_DATABASE_URL')
            ],
        ],
    ],
];
