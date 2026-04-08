<?php

use App\Space\ModuleInstaller;
use Illuminate\Support\Facades\File;

it('rejects zip slip entries during unzip', function () {
    $tempBase = sys_get_temp_dir().'/inv-zip-'.uniqid('', true);
    File::makeDirectory($tempBase, 0755, true);

    $zipPath = $tempBase.'/upload.zip';
    $zip = new ZipArchive;
    expect($zip->open($zipPath, ZipArchive::CREATE))->toBeTrue();
    $zip->addFromString('../evil.txt', 'nope');
    $zip->close();

    ModuleInstaller::unzip('WhiteLabel', $zipPath);
})->throws(RuntimeException::class);

it('stages and normalizes extracted module without mutating unrelated folders', function () {
    $extractDir = sys_get_temp_dir().'/inv-extract-'.uniqid('', true);
    File::makeDirectory($extractDir, 0755, true);

    // Simulate an extracted ZIP containing two module folders.
    File::makeDirectory($extractDir.'/WhiteLabel', 0755, true);
    File::put($extractDir.'/WhiteLabel/module.json', json_encode(['name' => 'WhiteLabel']));
    File::put($extractDir.'/WhiteLabel/App.php', 'ok');

    File::makeDirectory($extractDir.'/Other', 0755, true);
    File::put($extractDir.'/Other/module.json', json_encode(['name' => 'Other']));

    $ref = new ReflectionClass(ModuleInstaller::class);
    $method = $ref->getMethod('prepareNormalizedModuleDirectory');
    $method->setAccessible(true);

    $normalizedPath = (string) $method->invoke(null, 'WhiteLabel', $extractDir);

    expect(File::exists($normalizedPath.'/module.json'))->toBeTrue();
    expect(File::exists($normalizedPath.'/App.php'))->toBeTrue();
    expect(File::exists(dirname($normalizedPath).'/Other/module.json'))->toBeTrue();

    File::deleteDirectory($extractDir);
    File::deleteDirectory(dirname($normalizedPath));
});
