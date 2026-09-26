<?php

use App\Platform\Modules\Runtime\ModuleCompatibility;
use App\Platform\Modules\Runtime\OpcacheReset;

it('does nothing from the command line, where it cannot reach FPM', function () {
    expect(OpcacheReset::afterCodeChange())->toBeFalse();
});

it('explains why a compatibility block does not fit', function () {
    config(['app.version' => '3.0.0-alpha.9', 'invoiceshelf.marketplace.module_api_version' => '1.3.0']);

    expect(ModuleCompatibility::problems(['invoiceshelf' => '>=3.0.0-alpha.2 <4.0.0', 'module_api' => '^1.3.0']))->toBe([])
        ->and(ModuleCompatibility::problems(['invoiceshelf' => '>=3.1.0', 'module_api' => '^1.3.0']))->toHaveCount(1)
        ->and(ModuleCompatibility::problems(['module_api' => '^2.0.0']))->toHaveCount(1)
        ->and(ModuleCompatibility::problems(['module_api' => '^1.3.0', 'extensions' => ['ext-not-a-real-extension']]))->toHaveCount(1)
        ->and(ModuleCompatibility::problems('garbage'))->toHaveCount(1);
});
