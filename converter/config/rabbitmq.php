<?php

return [

    /*
    |--------------------------------------------------------------------------
    | RabbitMQ Connection
    |--------------------------------------------------------------------------
    |
    | Configuration for connecting to RabbitMQ message broker.
    |
    */

    'host' => env('RABBITMQ_HOST', 'rabbitmq'),
    'port' => env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER', 'guest'),
    'password' => env('RABBITMQ_PASSWORD', 'guest'),
    'vhost' => env('RABBITMQ_VHOST', '/'),

    /*
    |--------------------------------------------------------------------------
    | Connection Retry Settings
    |--------------------------------------------------------------------------
    */

    'retry' => [
        'max_attempts' => env('RABBITMQ_RETRY_MAX_ATTEMPTS', 10),
        'delay_seconds' => env('RABBITMQ_RETRY_DELAY', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    */

    'queues' => [
        'video' => [
            'name' => env('VIDEO_QUEUE', 'video'),
            'durable' => true,
            'auto_delete' => false,
            'prefetch_count' => 1,
        ],
        'mp3' => [
            'name' => env('MP3_QUEUE', 'mp3'),
            'durable' => true,
            'auto_delete' => false,
            'prefetch_count' => 1,
        ],
    ],

];
