<?php

declare(strict_types=1);

return [
    'name' => '悍将三国 (Modern Workerman Edition)',
    'env' => 'dev',
    'debug' => true,
    'http' => [
        'listen' => 'http://0.0.0.0:18100',
        'count' => 4,
        'public_path' => dirname(__DIR__) . '/public',
    ],
    'server_base_port' => 18100,
];
