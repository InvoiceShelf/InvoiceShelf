<?php

// Caches and clears the compiled views, which parallel tests share, so it
// runs in the serial isolated group.

use App\Platform\Modules\ModuleServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\View;

uses()->group('isolated', 'serial-only');

afterEach(function () {
    Artisan::call('view:clear');
});

test('a module that ships no views does not stop the views from being cached', function () {
    $missing = base_path('Modules/Viewless/resources/views');
    View::addNamespace('viewless', $missing);

    app()->getProvider(ModuleServiceProvider::class)->dropMissingModuleViewPaths();

    expect(View::getFinder()->getHints()['viewless'])->toBe([])
        ->and(Artisan::call('view:cache'))->toBe(0);
});

test('view directories outside the modules are left alone', function () {
    $elsewhere = storage_path('app/nowhere-'.uniqid());
    View::addNamespace('elsewhere', $elsewhere);

    app()->getProvider(ModuleServiceProvider::class)->dropMissingModuleViewPaths();

    expect(View::getFinder()->getHints()['elsewhere'])->toBe([$elsewhere]);
});
