<?php

use App\Models\FileDisk;

test('set filesystem configures s3 compatible root for generated pdf storage', function () {
    FileDisk::setFilesystem(collect([
        'endpoint' => 'http://minio:9000',
        'key' => 'invoiceshelf',
        'secret' => 'invoiceshelf123',
        'region' => 'us-east-1',
        'bucket' => 'invoiceshelf-local',
        'root' => 'generated-pdfs',
    ]), 's3compat');

    expect(config('filesystems.default'))->toBe('temp_s3compat')
        ->and(config('filesystems.disks.temp_s3compat.endpoint'))->toBe('http://minio:9000')
        ->and(config('filesystems.disks.temp_s3compat.bucket'))->toBe('invoiceshelf-local')
        ->and(config('filesystems.disks.temp_s3compat.root'))->toBe('generated-pdfs');
});
