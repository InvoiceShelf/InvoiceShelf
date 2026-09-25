<?php

use App\Domains\Accounts\Models\User;
use App\Platform\Pdf\Application\FontService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

/**
 * On-demand font packages: fetched from pinned URLs and checked against a
 * sha256 per file, optionally baked into the image ahead of time
 * (PDF_FONTS_PATH, pdf:fonts:install), and never fetched at run time when
 * PDF_FONTS_DOWNLOAD is off.
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);

    $this->storageFonts = storage_path('fonts');
    $this->baked = storage_path('framework/testing/baked-fonts');
    $this->hebrew = FontService::FONT_PACKAGES['noto-sans-hebrew'];

    foreach ($this->hebrew['files'] as $entry) {
        File::delete($this->storageFonts.'/'.$entry['file']);
    }
    File::deleteDirectory($this->baked);
});

afterEach(function () {
    foreach ($this->hebrew['files'] as $entry) {
        File::delete($this->storageFonts.'/'.$entry['file']);
    }
    File::deleteDirectory($this->baked);
});

/**
 * Serve the given bytes for every file of a package.
 */
function fakeFontDownloads(array $package, callable $bytesFor): void
{
    $responses = [];
    foreach ($package['files'] as $entry) {
        $responses[$entry['url']] = Http::response($bytesFor($entry));
    }
    Http::fake($responses);
}

test('every downloadable font is pinned and carries a checksum', function () {
    foreach (FontService::FONT_PACKAGES as $package) {
        if (! empty($package['bundled'])) {
            continue;
        }

        foreach ($package['files'] as $entry) {
            expect($entry['url'])->not->toMatch('~/raw/(master|main)/~')
                ->and($entry['sha256'])->toMatch('/^[0-9a-f]{64}$/');
        }
    }
});

test('a downloaded font is kept only when its checksum matches', function () {
    $service = app(FontService::class);
    $package = $this->hebrew;
    $package['files'][0]['sha256'] = hash('sha256', 'regular');
    $package['files'][1]['sha256'] = hash('sha256', 'bold');

    fakeFontDownloads($package, fn (array $entry) => str_contains($entry['file'], 'Bold') ? 'tampered' : 'regular');

    expect(fn () => $service->downloadPackage($package))->toThrow(RuntimeException::class, 'NotoSansHebrew-Bold.ttf');

    expect(File::get($this->storageFonts.'/NotoSansHebrew-Regular.ttf'))->toBe('regular')
        ->and(File::exists($this->storageFonts.'/NotoSansHebrew-Bold.ttf'))->toBeFalse();
});

test('baked fonts are used before anything is downloaded', function () {
    File::ensureDirectoryExists($this->baked);
    foreach ($this->hebrew['files'] as $entry) {
        File::put($this->baked.'/'.$entry['file'], 'baked');
    }
    config(['pdf.fonts.path' => $this->baked]);
    Http::fake();

    $service = app(FontService::class);
    $service->ensureFontsForLocale('he');

    Http::assertNothingSent();
    expect($service->isInstalled($this->hebrew))->toBeTrue()
        ->and($service->getInstalledFontFilePaths()['NotoSansHebrew-Regular.ttf'])->toBe($this->baked.'/NotoSansHebrew-Regular.ttf')
        ->and($service->getFontFamilyForLocale('he'))->toBe('"NotoSansHebrew"');
});

test('with downloads off, a missing font falls back instead of being fetched', function () {
    config(['pdf.fonts.download' => false]);
    Http::fake();

    $service = app(FontService::class);
    $service->ensureFontsForLocale('he');

    Http::assertNothingSent();
    expect($service->isInstalled($this->hebrew))->toBeFalse()
        ->and($service->getFontFamilyForLocale('he'))->toBe('"NotoSans"')
        ->and(collect($service->getPackageStatuses())->firstWhere('key', 'noto-sans-hebrew')['downloadable'])->toBeFalse();
});

test('pdf:fonts:install bakes packages into a directory', function () {
    config(['pdf.fonts.download' => false]);
    $package = FontService::FONT_PACKAGES['noto-sans-hebrew'];
    Http::fake(collect($package['files'])->mapWithKeys(fn (array $entry) => [
        $entry['url'] => Http::response('font bytes'),
    ])->all());

    // The fake bytes cannot match the real checksums.
    $this->artisan('pdf:fonts:install', ['packages' => ['noto-sans-hebrew'], '--path' => $this->baked])
        ->assertFailed();
    expect(File::exists($this->baked.'/NotoSansHebrew-Regular.ttf'))->toBeFalse();

    $this->artisan('pdf:fonts:install', ['packages' => ['no-such-package']])->assertFailed();
    $this->artisan('pdf:fonts:install')->assertFailed();

    // With checksums the bytes can match, the same run bakes the package.
    $package['files'] = array_map(fn (array $entry) => [...$entry, 'sha256' => hash('sha256', 'font bytes')], $package['files']);
    app()->instance(FontService::class, new class($package) extends FontService
    {
        public function __construct(private array $hebrew) {}

        public function packages(): array
        {
            return ['noto-sans-hebrew' => $this->hebrew];
        }
    });

    $this->artisan('pdf:fonts:install', ['--all' => true, '--path' => $this->baked])->assertSuccessful();
    expect(File::get($this->baked.'/NotoSansHebrew-Regular.ttf'))->toBe('font bytes')
        ->and(File::exists($this->baked.'/NotoSansHebrew-Bold.ttf'))->toBeTrue();
});

test('the admin cannot download fonts when downloads are off', function () {
    config(['pdf.fonts.download' => false]);
    Http::fake();
    Sanctum::actingAs(User::query()->where('role', 'super admin')->firstOrFail(), ['*']);

    postJson('/api/v1/fonts/noto-sans-hebrew/install')->assertStatus(409);
    Http::assertNothingSent();
});
