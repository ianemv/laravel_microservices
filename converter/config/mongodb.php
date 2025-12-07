<?php

return [

    /*
    |--------------------------------------------------------------------------
    | MongoDB Connection
    |--------------------------------------------------------------------------
    |
    | Configuration for connecting to MongoDB for GridFS file storage.
    |
    */

    'host' => env('MONGODB_HOST', 'host.minikube.internal'),
    'port' => env('MONGODB_PORT', 27017),
    'username' => env('MONGODB_USERNAME'),
    'password' => env('MONGODB_PASSWORD'),
    'auth_database' => env('MONGODB_AUTH_DATABASE', 'admin'),

    /*
    |--------------------------------------------------------------------------
    | Database Name
    |--------------------------------------------------------------------------
    |
    | Single database for all GridFS buckets (matching gateway configuration).
    |
    */

    'database' => env('MONGODB_DATABASE', 'mp3converter'),

    /*
    |--------------------------------------------------------------------------
    | GridFS Buckets
    |--------------------------------------------------------------------------
    |
    | Configuration for GridFS buckets within the single database.
    | Both videos and mp3s use the same database but different bucket names.
    |
    */

    'databases' => [
        'videos' => [
            'name' => env('MONGODB_DATABASE', 'mp3converter'),
            'bucket' => env('MONGODB_VIDEOS_BUCKET', 'videos'),
        ],
        'mp3s' => [
            'name' => env('MONGODB_DATABASE', 'mp3converter'),
            'bucket' => env('MONGODB_MP3S_BUCKET', 'mp3s'),
        ],
    ],

];
