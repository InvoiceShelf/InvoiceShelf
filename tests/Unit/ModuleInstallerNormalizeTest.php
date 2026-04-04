<?php

use App\Space\ModuleInstaller;
use Illuminate\Support\Facades\File;

afterEach(function () {
    if (isset($this->tempBase) && File::isDirectory($this->tempBase)) {
        File::deleteDirectory($this->tempBase);
    }
});

function makeTempModuleRoot(): string
{
    $base = sys_get_temp_dir().'/inv-mod-'.uniqid('', true);
    File::makeDirectory($base.'/Modules', 0755, true);

    return $base;
}

it('leaves canonical layout unchanged', function () {
    $this->tempBase = makeTempModuleRoot();
    File::makeDirectory($this->tempBase.'/Modules/WhiteLabel', 0755, true);
    File::put($this->tempBase.'/Modules/WhiteLabel/module.json', json_encode(['name' => 'WhiteLabel']));

    ModuleInstaller::normalizeModuleInstallLocation('WhiteLabel', $this->tempBase);

    expect(File::exists($this->tempBase.'/Modules/WhiteLabel/module.json'))->toBeTrue();
});

it('promotes flat module.json into studly folder', function () {
    $this->tempBase = makeTempModuleRoot();
    File::put($this->tempBase.'/Modules/module.json', json_encode(['name' => 'WhiteLabel']));
    File::makeDirectory($this->tempBase.'/Modules/app', 0755, true);
    File::put($this->tempBase.'/Modules/app/Foo.php', 'ok');

    ModuleInstaller::normalizeModuleInstallLocation('WhiteLabel', $this->tempBase);

    expect(File::exists($this->tempBase.'/Modules/WhiteLabel/module.json'))->toBeTrue();
    expect(File::exists($this->tempBase.'/Modules/WhiteLabel/app/Foo.php'))->toBeTrue();
    expect(File::exists($this->tempBase.'/Modules/module.json'))->toBeFalse();
});

it('renames folder when module.json name matches but directory name does not', function () {
    $this->tempBase = makeTempModuleRoot();
    File::makeDirectory($this->tempBase.'/Modules/module-whitelabel', 0755, true);
    File::put($this->tempBase.'/Modules/module-whitelabel/module.json', json_encode(['name' => 'WhiteLabel']));
    File::put($this->tempBase.'/Modules/module-whitelabel/App.php', 'x');

    ModuleInstaller::normalizeModuleInstallLocation('WhiteLabel', $this->tempBase);

    expect(File::exists($this->tempBase.'/Modules/WhiteLabel/module.json'))->toBeTrue();
    expect(File::exists($this->tempBase.'/Modules/WhiteLabel/App.php'))->toBeTrue();
    expect(File::isDirectory($this->tempBase.'/Modules/module-whitelabel'))->toBeFalse();
});

it('promotes double nested studly folder', function () {
    $this->tempBase = makeTempModuleRoot();
    File::makeDirectory($this->tempBase.'/Modules/WhiteLabel/WhiteLabel', 0755, true);
    File::put($this->tempBase.'/Modules/WhiteLabel/WhiteLabel/module.json', json_encode(['name' => 'WhiteLabel']));
    File::put($this->tempBase.'/Modules/WhiteLabel/WhiteLabel/routes.php', '');

    ModuleInstaller::normalizeModuleInstallLocation('WhiteLabel', $this->tempBase);

    expect(File::exists($this->tempBase.'/Modules/WhiteLabel/module.json'))->toBeTrue();
    expect(File::exists($this->tempBase.'/Modules/WhiteLabel/routes.php'))->toBeTrue();
});

it('throws when module manifest cannot be resolved', function () {
    $this->tempBase = makeTempModuleRoot();
    File::makeDirectory($this->tempBase.'/Modules/Other', 0755, true);
    File::put($this->tempBase.'/Modules/Other/module.json', json_encode(['name' => 'Other']));

    ModuleInstaller::normalizeModuleInstallLocation('WhiteLabel', $this->tempBase);
})->throws(RuntimeException::class);

it('throws when flat module.json name mismatches', function () {
    $this->tempBase = makeTempModuleRoot();
    File::put($this->tempBase.'/Modules/module.json', json_encode(['name' => 'Payments']));

    ModuleInstaller::normalizeModuleInstallLocation('WhiteLabel', $this->tempBase);
})->throws(RuntimeException::class);
