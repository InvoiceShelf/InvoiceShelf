<?php

use App\Http\Resources\ModuleResource;
use App\Models\Module as InstalledModule;
use Illuminate\Http\Request;

it('maps the marketplace payload shape expected by the admin modules ui', function () {
    $payload = (object) [
        'id' => 7,
        'slug' => 'sales-tax-us',
        'name' => 'Sales Tax US',
        'module_name' => 'SalesTaxUs',
        'access_tier' => 'premium',
        'cover' => 'https://example.com/cover.png',
        'short_description' => 'Short description',
        'long_description' => 'Long description',
        'highlights' => ['Fast install'],
        'screenshots' => [['url' => 'https://example.com/shot.png', 'title' => 'Shot']],
        'faq' => [['question' => 'Q', 'answer' => 'A']],
        'links' => [['icon' => 'BookOpenIcon', 'label' => 'Docs', 'link' => 'https://example.com/docs']],
        'video_link' => null,
        'video_thumbnail' => null,
        'type' => 'integration',
        'is_dev' => false,
        'author_name' => 'InvoiceShelf',
        'author_avatar' => 'https://example.com/avatar.png',
        'latest_module_version' => '1.2.0',
        'latest_module_version_updated_at' => now()->toIso8601String(),
        'latest_min_invoiceshelf_version' => '3.0.0',
        'latest_module_checksum_sha256' => hash('sha256', 'sales-tax-us-1.2.0'),
        'installed_module_version' => null,
        'installed_module_version_updated_at' => null,
        'installed' => false,
        'enabled' => false,
        'update_available' => false,
        'reviews' => [],
        'average_rating' => null,
        'monthly_price' => null,
        'yearly_price' => null,
        'purchased' => true,
        'license' => null,
    ];

    $data = (new ModuleResource($payload))->toArray(Request::create('/'));

    expect($data)->toMatchArray([
        'slug' => 'sales-tax-us',
        'module_name' => 'SalesTaxUs',
        'access_tier' => 'premium',
        'author_name' => 'InvoiceShelf',
        'latest_module_version' => '1.2.0',
        'latest_min_invoiceshelf_version' => '3.0.0',
    ]);
});

it('overlays installed module state and update availability locally', function () {
    InstalledModule::query()->create([
        'name' => 'SalesTaxUs',
        'version' => '1.0.0',
        'installed' => true,
        'enabled' => true,
    ]);

    $payload = (object) [
        'id' => 7,
        'slug' => 'sales-tax-us',
        'name' => 'Sales Tax US',
        'module_name' => 'SalesTaxUs',
        'access_tier' => 'public',
        'cover' => null,
        'short_description' => null,
        'long_description' => null,
        'highlights' => null,
        'screenshots' => null,
        'faq' => null,
        'links' => null,
        'video_link' => null,
        'video_thumbnail' => null,
        'type' => null,
        'is_dev' => false,
        'author_name' => 'InvoiceShelf',
        'author_avatar' => null,
        'latest_module_version' => '1.1.0',
        'latest_module_version_updated_at' => now()->toIso8601String(),
        'latest_min_invoiceshelf_version' => '3.0.0',
        'latest_module_checksum_sha256' => hash('sha256', 'sales-tax-us-1.1.0'),
        'reviews' => [],
        'average_rating' => null,
        'monthly_price' => null,
        'yearly_price' => null,
        'purchased' => true,
        'license' => null,
    ];

    $data = (new ModuleResource($payload))->toArray(Request::create('/'));

    expect($data['installed'])->toBeTrue()
        ->and($data['enabled'])->toBeTrue()
        ->and($data['installed_module_version'])->toBe('1.0.0')
        ->and($data['update_available'])->toBeTrue();
});
