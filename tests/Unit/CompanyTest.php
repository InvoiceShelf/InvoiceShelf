<?php

use App\Models\Company;
use App\Models\FileDisk;
use App\Space\ImageUtils;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

test('company has many customers', function () {
    $company = Company::factory()->hasCustomers()->create();

    $this->assertTrue($company->customers()->exists());
});

test('company has many company settings', function () {
    $company = Company::factory()->hasSettings(5)->create();

    $this->assertCount(5, $company->settings);

    $this->assertTrue($company->settings()->exists());
});

test('a company belongs to many users', function () {
    $company = Company::factory()->hasUsers(5)->create();

    $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $company->users);
});

test('company logo path uses the logo media disk when default pdf disk is remote', function () {
    config(['media-library.disk_name' => 'local']);

    $company = Company::firstOrFail();

    Storage::disk('local')->put(
        'testing/company-logo.png',
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9s2Yx2wAAAAASUVORK5CYII=')
    );

    $company->addMedia(Storage::disk('local')->path('testing/company-logo.png'))
        ->toMediaCollection('logo', 'local');

    FileDisk::query()->update(['set_as_default' => false]);

    FileDisk::create([
        'name' => 'MinIO',
        'driver' => 's3compat',
        'set_as_default' => true,
        'company_id' => $company->id,
        'credentials' => [
            'endpoint' => 'http://minio:9000',
            'key' => 'invoiceshelf',
            'secret' => 'invoiceshelf123',
            'region' => 'us-east-1',
            'bucket' => 'invoiceshelf-local',
            'root' => 'generated-pdfs',
        ],
    ]);

    $logo = $company->getMedia('logo')->first();

    expect($company->logo_path)->toBe($logo->getPath());
});

test('image utils can encode remote images', function () {
    Http::fake([
        'https://example.com/logo.png' => Http::response('image-bytes', 200, [
            'Content-Type' => 'image/png; charset=binary',
        ]),
    ]);

    expect(ImageUtils::toBase64Src('https://example.com/logo.png'))
        ->toBe('data:image/png;base64,'.base64_encode('image-bytes'));
});
