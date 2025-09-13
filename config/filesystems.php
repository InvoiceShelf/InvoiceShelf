<?php

return [

    'cloud' => env('FILESYSTEM_CLOUD', 's3'),

    'disks' => [
        's3' => [
            'driver' => 's3',
            'key' => env('AWS_KEY'),
            'secret' => env('AWS_SECRET'),
            'region' => env('AWS_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'root' => env('AWS_ROOT'),
        ],

        's3compat' => [
            'driver' => 's3',
            'endpoint' => env('S3_COMPAT_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'key' => env('S3_COMPAT_KEY'),
            'secret' => env('S3_COMPAT_SECRET'),
            'region' => env('S3_COMPAT_REGION'),
            'bucket' => env('S3_COMPAT_BUCKET'),
        ],

        'media' => [
            'driver' => 'local',
            'root' => public_path('media'),
        ],

        'doSpaces' => [
            'type' => 'AwsS3',
            'driver' => 's3',
            'key' => env('DO_SPACES_KEY'),
            'secret' => env('DO_SPACES_SECRET'),
            'region' => env('DO_SPACES_REGION'),
            'bucket' => env('DO_SPACES_BUCKET'),
            'root' => env('DO_SPACES_ROOT'),
            'endpoint' => env('DO_SPACES_ENDPOINT'),
            'use_path_style_endpoint' => false,
        ],

        'dropbox' => [
            'driver' => 'dropbox',
            'type' => 'DropboxV2',
            'token' => env('DROPBOX_TOKEN'),
            'key' => env('DROPBOX_KEY'),
            'secret' => env('DROPBOX_SECRET'),
            'app' => env('DROPBOX_APP'),
            'root' => env('DROPBOX_ROOT'),
        ],

        'views' => [
            'driver' => 'local',
            'root' => resource_path('views'),
        ],

        'pdf_templates' => [
            'driver' => 'local',
            'root' => storage_path('app/templates/pdf'),
        ],

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
            'report' => false,
        ],
    ],

];
