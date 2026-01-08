<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
            'report' => false,
        ],

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

        'media' => [
            'driver' => 'local',
            'root' => public_path('media'),
        ],

        'views' => [
            'driver' => 'local',
            'root' => resource_path('views'),
        ],

        'pdf_templates' => [
            'driver' => 'local',
            'root' => storage_path('app/templates/pdf'),
        ],
    ],

];
