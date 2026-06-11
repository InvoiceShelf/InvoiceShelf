<?php

use App\Space\Updater;
use Illuminate\Support\Facades\File;

/*
 * Exercises Updater::cleanStaleFiles() against a throwaway sandbox directory
 * (never the live install) so we can prove the manifest allow-list behaviour:
 * files not listed in manifest.json are removed, while listed files, protected
 * paths, and the manifest itself survive. cleanStaleFiles() accepts an explicit
 * base path purely so these tests can run safely.
 */

beforeEach(function () {
    $this->sandbox = sys_get_temp_dir().'/updater-test-'.uniqid();
    File::makeDirectory($this->sandbox, 0755, true);
});

afterEach(function () {
    if (File::isDirectory($this->sandbox)) {
        File::deleteDirectory($this->sandbox);
    }
});

function writeSandboxFile(string $base, string $relative, string $contents = 'x'): void
{
    $path = $base.'/'.$relative;
    File::ensureDirectoryExists(dirname($path));
    File::put($path, $contents);
}

test('removes stale files not present in the manifest', function () {
    writeSandboxFile($this->sandbox, 'app/Keep.php');
    writeSandboxFile($this->sandbox, 'app/Stale.php');
    writeSandboxFile($this->sandbox, 'public/old-chunk.js');

    File::put($this->sandbox.'/manifest.json', json_encode(['app/Keep.php']));

    $result = Updater::cleanStaleFiles($this->sandbox);

    expect($result['success'])->toBeTrue();
    expect($result['cleaned'])->toBe(2);
    expect(File::exists($this->sandbox.'/app/Keep.php'))->toBeTrue();
    expect(File::exists($this->sandbox.'/app/Stale.php'))->toBeFalse();
    expect(File::exists($this->sandbox.'/public/old-chunk.js'))->toBeFalse();
});

test('never deletes protected paths even when absent from the manifest', function () {
    writeSandboxFile($this->sandbox, 'storage/logs/laravel.log');
    writeSandboxFile($this->sandbox, '.env', 'APP_KEY=base64:xxx');
    writeSandboxFile($this->sandbox, 'vendor/autoload.php');
    writeSandboxFile($this->sandbox, 'app/Stale.php');

    // Manifest lists none of the above.
    File::put($this->sandbox.'/manifest.json', json_encode(['app/Keep.php']));

    Updater::cleanStaleFiles($this->sandbox);

    expect(File::exists($this->sandbox.'/storage/logs/laravel.log'))->toBeTrue();
    expect(File::exists($this->sandbox.'/.env'))->toBeTrue();
    expect(File::exists($this->sandbox.'/vendor/autoload.php'))->toBeTrue();
    expect(File::exists($this->sandbox.'/manifest.json'))->toBeTrue();
    // ...but unprotected stale files are still removed.
    expect(File::exists($this->sandbox.'/app/Stale.php'))->toBeFalse();
});

test('prunes directories left empty after cleaning', function () {
    writeSandboxFile($this->sandbox, 'old-feature/View.php');

    File::put($this->sandbox.'/manifest.json', json_encode(['app/Keep.php']));
    writeSandboxFile($this->sandbox, 'app/Keep.php');

    Updater::cleanStaleFiles($this->sandbox);

    expect(File::exists($this->sandbox.'/old-feature/View.php'))->toBeFalse();
    expect(File::isDirectory($this->sandbox.'/old-feature'))->toBeFalse();
});

test('is a safe no-op when no manifest is present', function () {
    writeSandboxFile($this->sandbox, 'app/Anything.php');

    $result = Updater::cleanStaleFiles($this->sandbox);

    expect($result)->toBe(['success' => true, 'cleaned' => 0]);
    expect(File::exists($this->sandbox.'/app/Anything.php'))->toBeTrue();
});

test('reports an error for an invalid manifest', function () {
    writeSandboxFile($this->sandbox, 'app/Anything.php');
    File::put($this->sandbox.'/manifest.json', 'not-json');

    $result = Updater::cleanStaleFiles($this->sandbox);

    expect($result['success'])->toBeFalse();
    // Nothing is deleted when the manifest cannot be parsed.
    expect(File::exists($this->sandbox.'/app/Anything.php'))->toBeTrue();
});
